<template>
  <nav class="nav">
    <h1 class="logo">CryptoX</h1>

    <div class="links">
      <router-link to="/" class="nav-link">Markets</router-link>
      <router-link to="/" class="nav-link">Trade</router-link>

      <!-- Loading state -->
      <span v-if="loading" class="nav-link muted">
        Checking…
      </span>

      <!-- Not logged in -->
      <router-link
        v-else-if="!isLoggedIn"
        to="/login"
        class="btn-login"
      >
        Login
      </router-link>

      <!-- Logged in - Show Balance -->
      <template v-else>
        <div class="balance-section">
          <div class="balance-label">Balance</div>
          <div class="balance-value" :class="{ positive: liveBalance >= baseBalance, negative: liveBalance < baseBalance }">
            ${{ formatBalance(liveBalance) }}
          </div>
          <div v-if="unrealizedPL !== 0" class="unrealized-pl" :class="{ positive: unrealizedPL > 0, negative: unrealizedPL < 0 }">
            {{ unrealizedPL > 0 ? '+' : '' }}{{ formatBalance(unrealizedPL) }}
          </div>
        </div>
        <button
          class="btn-login"
          @click="logout"
        >
          Logout
        </button>
      </template>
    </div>
  </nav>
</template>

<script>
import axios from 'axios'

export default {
  name: 'Navbar',

  data() {
    return {
      isLoggedIn: false,
      user: null,
      loading: true,
      baseBalance: 0,
      liveBalance: 0,
      unrealizedPL: 0,
      wallets: [],
      openOrders: [],
      priceSocket: null,
      prices: {}, // Store current market prices
      balanceInterval: null,
      wsConnecting: false,
      wsReconnectTimeout: null,
      reconnectAttempts: 0,
      maxReconnectAttempts: 5,
    }
  },

  methods: {
    async checkAuth() {
      this.loading = true
      try {
        const res = await axios.get('/user')
        this.isLoggedIn = true
        this.user = res.data
        this.fetchBalance()
      } catch {
        this.isLoggedIn = false
        this.user = null
        this.stopBalanceUpdates()
      } finally {
        this.loading = false
      }
    },

    async fetchBalance() {
      if (!this.isLoggedIn) return

      try {
        const res = await axios.get('/api/balance')
        const data = res.data

        this.wallets = data.wallets || []
        this.openOrders = data.open_orders || []

        // Calculate base balance (sum of all USDT wallets)
        this.baseBalance = this.wallets
          .filter(w => w.currency === 'USDT')
          .reduce((sum, w) => sum + (w.balance || 0), 0)

        // Calculate live balance with current prices
        this.calculateLiveBalance()

        // Connect WebSocket after data is loaded (only if not already connecting)
        if (!this.wsConnecting && !this.priceSocket) {
          // Small delay to ensure data is set
          setTimeout(() => {
            this.connectPriceWebSocket()
          }, 500)
        }
      } catch (err) {
        console.error('Failed to fetch balance:', err)
      }
    },

    calculateLiveBalance() {
      // Calculate total balance including all currencies converted to USDT
      let totalBalance = 0

      this.wallets.forEach(wallet => {
        if (wallet.currency === 'USDT') {
          totalBalance += wallet.balance || 0
        } else {
          // Convert other currencies to USDT using live prices
          const symbol = `${wallet.currency}USDT`
          const currentPrice = this.prices[symbol] || 0
          const balance = wallet.balance || 0
          totalBalance += balance * currentPrice
        }
      })

      this.baseBalance = totalBalance

      // Calculate unrealized P/L for open orders
      let unrealizedPL = 0

      if (this.openOrders.length > 0) {
        this.openOrders.forEach(order => {
          const currentPrice = this.prices[order.symbol] || order.market_price || order.price
          const remaining = order.remaining_amount || 0
          const orderPrice = order.price

          if (order.type === 'buy') {
            // For buy orders: unrealized gain = (current_price - order_price) * remaining
            // This represents the potential gain if we could sell at current price
            const potentialValue = currentPrice * remaining
            const lockedValue = orderPrice * remaining
            unrealizedPL += (potentialValue - lockedValue)
          } else if (order.type === 'sell') {
            // For sell orders: unrealized gain = (order_price - current_price) * remaining
            // If current price is lower, we gain. If higher, we lose.
            const lockedValue = orderPrice * remaining
            const currentValue = currentPrice * remaining
            unrealizedPL += (lockedValue - currentValue)
          }
        })
      }

      this.unrealizedPL = unrealizedPL
      this.liveBalance = this.baseBalance + unrealizedPL
    },

    connectPriceWebSocket() {
      if (!this.isLoggedIn || this.wsConnecting) return

      // Close existing connection if any
      if (this.priceSocket) {
        try {
          this.priceSocket.close()
        } catch (e) {
          // Ignore errors when closing
        }
        this.priceSocket = null
      }

      // Clear any pending reconnect
      if (this.wsReconnectTimeout) {
        clearTimeout(this.wsReconnectTimeout)
        this.wsReconnectTimeout = null
      }

      // Get unique symbols from open orders
      const orderSymbols = this.openOrders.length > 0 
        ? [...new Set(this.openOrders.map(o => {
            const sym = o.symbol ? o.symbol.toLowerCase() : ''
            return sym && sym.endsWith('usdt') ? sym : ''
          }).filter(s => s))]
        : []

      // Get symbols from wallets (for currency conversion)
      const walletSymbols = this.wallets
        .filter(w => w.currency && w.currency !== 'USDT')
        .map(w => {
          const currency = w.currency.toUpperCase()
          return `${currency}USDT`.toLowerCase()
        })
        .filter(s => s && s.endsWith('usdt'))

      // Combine and deduplicate
      const allSymbols = [...new Set([...orderSymbols, ...walletSymbols])]
      
      if (allSymbols.length === 0) {
        // Still calculate balance even without orders
        this.calculateLiveBalance()
        this.wsConnecting = false
        return
      }

      // Validate symbols (must be valid Binance symbols)
      const validSymbols = allSymbols.filter(s => {
        // Basic validation - should be like btcusdt, ethusdt, etc.
        return s.length >= 6 && s.length <= 12 && s.endsWith('usdt')
      })

      if (validSymbols.length === 0) {
        this.calculateLiveBalance()
        this.wsConnecting = false
        return
      }

      // Create streams for all symbols
      const streams = validSymbols.map(s => `${s}@ticker`).join('/')
      const wsUrl = `wss://stream.binance.com:9443/stream?streams=${streams}`

      this.wsConnecting = true
      this.reconnectAttempts = 0

      try {
        const ws = new WebSocket(wsUrl)

        ws.onopen = () => {
          this.wsConnecting = false
          this.reconnectAttempts = 0
          this.priceSocket = ws
        }

        ws.onmessage = (event) => {
          try {
            const data = JSON.parse(event.data)
            
            if (data.stream && data.data && data.data.c) {
              const symbol = data.stream.split('@')[0].toUpperCase()
              const price = parseFloat(data.data.c)
              
              if (!isNaN(price) && price > 0) {
                this.prices[symbol] = price
                this.calculateLiveBalance()
              }
            }
          } catch (e) {
            console.error('Error parsing WebSocket message:', e)
          }
        }

        ws.onerror = (err) => {
          console.error('Price WebSocket error:', err)
          this.wsConnecting = false
        }

        ws.onclose = (event) => {
          this.wsConnecting = false
          this.priceSocket = null

          // Only reconnect if it wasn't a manual close and we're still logged in
          if (this.isLoggedIn && event.code !== 1000) {
            this.scheduleReconnect()
          }
        }

        // Set timeout for connection
        setTimeout(() => {
          if (ws.readyState === WebSocket.CONNECTING) {
            ws.close()
            this.wsConnecting = false
            this.scheduleReconnect()
          }
        }, 10000) // 10 second timeout

      } catch (err) {
        console.error('Failed to create WebSocket:', err)
        this.wsConnecting = false
        this.scheduleReconnect()
      }
    },

    scheduleReconnect() {
      if (!this.isLoggedIn) return
      if (this.reconnectAttempts >= this.maxReconnectAttempts) {
        console.warn('Max WebSocket reconnect attempts reached')
        return
      }

      this.reconnectAttempts++
      const delay = Math.min(3000 * this.reconnectAttempts, 30000) // Exponential backoff, max 30s

      this.wsReconnectTimeout = setTimeout(() => {
        if (this.isLoggedIn) {
          this.connectPriceWebSocket()
        }
      }, delay)
    },

    stopBalanceUpdates() {
      // Clear reconnect timeout
      if (this.wsReconnectTimeout) {
        clearTimeout(this.wsReconnectTimeout)
        this.wsReconnectTimeout = null
      }

      // Close WebSocket
      if (this.priceSocket) {
        try {
          this.priceSocket.close(1000, 'Manual close')
        } catch (e) {
          // Ignore errors
        }
        this.priceSocket = null
      }

      // Clear interval
      if (this.balanceInterval) {
        clearInterval(this.balanceInterval)
        this.balanceInterval = null
      }

      // Reset state
      this.wsConnecting = false
      this.reconnectAttempts = 0
      this.prices = {}
      this.baseBalance = 0
      this.liveBalance = 0
      this.unrealizedPL = 0
    },

    formatBalance(value) {
      if (value === null || value === undefined) return '0.00'
      return Math.abs(value).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      })
    },

    async logout() {
      if (!this.isLoggedIn) return

      try {
        await axios.post('/logout')
      } catch (err) {
        console.error('Logout failed:', err.response?.data || err)
      } finally {
        this.isLoggedIn = false
        this.user = null
        this.stopBalanceUpdates()

        // 🔥 tell app auth changed
        window.dispatchEvent(new Event('auth-updated'))

        this.$router.push('/login')
      }
    },
  },

  mounted() {
    // initial check (page load / refresh)
    this.checkAuth()

    // 🔥 listen for login/logout events
    window.addEventListener('auth-updated', this.checkAuth)
  },

    watch: {
    isLoggedIn(newVal) {
      if (newVal) {
        // Start balance updates when logged in
        this.fetchBalance()
        
        // Refresh balance every 5 seconds
        this.balanceInterval = setInterval(() => {
          this.fetchBalance()
        }, 5000)
      } else {
        this.stopBalanceUpdates()
      }
    },

    openOrders: {
      handler() {
        // Recalculate balance when orders change
        this.calculateLiveBalance()
        // Reconnect WebSocket if needed (debounced)
        if (this.isLoggedIn && !this.wsConnecting) {
          clearTimeout(this.wsReconnectTimeout)
          this.wsReconnectTimeout = setTimeout(() => {
            if (this.isLoggedIn) {
              this.connectPriceWebSocket()
            }
          }, 1000) // Debounce by 1 second
        }
      },
      deep: true
    },

    wallets: {
      handler() {
        // Recalculate balance when wallets change
        this.calculateLiveBalance()
        // Reconnect WebSocket if needed (debounced)
        if (this.isLoggedIn && !this.wsConnecting) {
          clearTimeout(this.wsReconnectTimeout)
          this.wsReconnectTimeout = setTimeout(() => {
            if (this.isLoggedIn) {
              this.connectPriceWebSocket()
            }
          }, 1000) // Debounce by 1 second
        }
      },
      deep: true
    }
  },

  beforeUnmount() {
    window.removeEventListener('auth-updated', this.checkAuth)
    this.stopBalanceUpdates()
  },
}
</script>


<style scoped>
.nav {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 48px;
  background-color: #0b0f1a;
  border-bottom: 1px solid #1f2933;
}

.logo {
  font-size: 26px;
  font-weight: 900;
  color: #ffffff;
}

.links {
  display: flex;
  align-items: center;
}

.nav-link {
  padding: 10px 18px;
  color: #cbd5e1;
  text-decoration: none;
  font-size: 14px;
}

.nav-link:hover {
  color: #ffffff;
}

.btn-login {
  margin-left: 16px;
  padding: 10px 22px;
  background: #ffffff;
  color: #000;
  border: none;
  font-weight: 600;
  cursor: pointer;
}

.btn-login:hover {
  background: #e5e7eb;
}

.muted {
  opacity: 0.6;
  cursor: default;
}

.balance-section {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  margin-right: 20px;
  padding: 8px 16px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 8px;
  min-width: 150px;
}

.balance-label {
  font-size: 11px;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.balance-value {
  font-size: 18px;
  font-weight: 700;
  color: #ffffff;
  font-variant-numeric: tabular-nums;
}

.balance-value.positive {
  color: #22c55e;
}

.balance-value.negative {
  color: #ef4444;
}

.unrealized-pl {
  font-size: 11px;
  font-weight: 600;
  margin-top: 2px;
  font-variant-numeric: tabular-nums;
}

.unrealized-pl.positive {
  color: #22c55e;
}

.unrealized-pl.negative {
  color: #ef4444;
}
</style>
