<template>
  <nav class="nav">
    <div class="logo-section">
      <div class="logo-icon">
        <svg viewBox="0 0 32 32" width="28" height="28">
          <circle cx="16" cy="16" r="14" fill="url(#coinGradient)" />
          <text x="16" y="21" text-anchor="middle" fill="#0a0e1a" font-size="14" font-weight="bold">₿</text>
          <defs>
            <linearGradient id="coinGradient" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#fbbf24" />
              <stop offset="100%" stop-color="#f59e0b" />
            </linearGradient>
          </defs>
        </svg>
      </div>
      <h1 class="logo">CryptoX</h1>
    </div>

    <div class="links">
      <router-link to="/" class="nav-link">
        <span class="nav-icon">📊</span>
        Markets
      </router-link>
      <router-link to="/orders" class="nav-link">
        <span class="nav-icon">📋</span>
        Orders
      </router-link>

      <!-- Loading state -->
      <span v-if="loading" class="nav-link muted">
        <span class="loading-spinner"></span>
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
        <div class="balance-container" @mouseenter="showDropdown = true" @mouseleave="showDropdown = false">
          <div class="balance-section">
            <div class="balance-header">
              <div class="live-dot"></div>
              <span class="balance-label">Portfolio</span>
            </div>
            <div class="balance-main">
              <span class="balance-currency">$</span>
              <span class="balance-value" :class="{ 'value-up': balanceDirection === 'up', 'value-down': balanceDirection === 'down' }">
                {{ formatBalance(liveBalance) }}
              </span>
            </div>
            <div v-if="unrealizedPL !== 0" class="unrealized-pl" :class="{ positive: unrealizedPL > 0, negative: unrealizedPL < 0 }">
              <span class="pl-icon">{{ unrealizedPL > 0 ? '▲' : '▼' }}</span>
              {{ unrealizedPL > 0 ? '+' : '' }}${{ formatBalance(Math.abs(unrealizedPL)) }}
            </div>
          </div>
          
          <!-- Wallet Dropdown -->
          <transition name="dropdown">
            <div v-if="showDropdown && wallets.length > 0" class="wallet-dropdown">
              <div class="dropdown-header">Your Assets</div>
              <div v-for="wallet in wallets" :key="wallet.currency" class="wallet-row">
                <div class="wallet-info">
                  <span class="wallet-currency">{{ wallet.currency }}</span>
                  <span class="wallet-available">Available: {{ formatBalance(wallet.available) }}</span>
                </div>
                <div class="wallet-values">
                  <span class="wallet-balance">{{ formatBalance(wallet.balance) }}</span>
                  <span v-if="wallet.locked_balance > 0" class="wallet-locked">
                    🔒 {{ formatBalance(wallet.locked_balance) }}
                  </span>
                </div>
              </div>
            </div>
          </transition>
        </div>
        
        <button
          class="btn-logout"
          @click="logout"
        >
          <span class="logout-icon">↪</span>
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
      previousBalance: 0,
      balanceDirection: null,
      unrealizedPL: 0,
      wallets: [],
      openOrders: [],
      priceSocket: null,
      prices: {},
      balanceInterval: null,
      wsConnecting: false,
      wsReconnectTimeout: null,
      reconnectAttempts: 0,
      maxReconnectAttempts: 5,
      showDropdown: false,
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
            const potentialValue = currentPrice * remaining
            const lockedValue = orderPrice * remaining
            unrealizedPL += (potentialValue - lockedValue)
          } else if (order.type === 'sell') {
            const lockedValue = orderPrice * remaining
            const currentValue = currentPrice * remaining
            unrealizedPL += (lockedValue - currentValue)
          }
        })
      }

      this.unrealizedPL = unrealizedPL
      const newBalance = this.baseBalance + unrealizedPL
      
      // Track balance direction for animation
      if (this.liveBalance !== 0 && newBalance !== this.liveBalance) {
        this.previousBalance = this.liveBalance
        this.balanceDirection = newBalance > this.liveBalance ? 'up' : 'down'
        // Reset direction after animation
        setTimeout(() => {
          this.balanceDirection = null
        }, 600)
      }
      
      this.liveBalance = newBalance
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
  padding: 16px 48px;
  background: linear-gradient(180deg, #0d1220 0%, #0b0f1a 100%);
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  position: relative;
  z-index: 1000;
}

.logo-section {
  display: flex;
  align-items: center;
  gap: 12px;
}

.logo-icon {
  animation: float 3s ease-in-out infinite;
}

@keyframes float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-3px); }
}

.logo {
  font-size: 24px;
  font-weight: 800;
  background: linear-gradient(135deg, #ffffff 0%, #94a3b8 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin: 0;
}

.links {
  display: flex;
  align-items: center;
  gap: 8px;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 16px;
  color: #94a3b8;
  text-decoration: none;
  font-size: 14px;
  font-weight: 500;
  border-radius: 8px;
  transition: all 0.2s ease;
}

.nav-link:hover {
  color: #ffffff;
  background: rgba(255, 255, 255, 0.05);
}

.nav-icon {
  font-size: 16px;
}

.muted {
  opacity: 0.6;
  cursor: default;
}

.loading-spinner {
  display: inline-block;
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.2);
  border-top-color: #ffffff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin-right: 8px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.btn-login {
  margin-left: 12px;
  padding: 10px 24px;
  background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
  color: #ffffff;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 4px 15px rgba(34, 197, 94, 0.3);
}

.btn-login:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(34, 197, 94, 0.4);
}

.btn-logout {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-left: 12px;
  padding: 10px 20px;
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.3);
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-logout:hover {
  background: rgba(239, 68, 68, 0.2);
  border-color: rgba(239, 68, 68, 0.5);
}

.logout-icon {
  font-size: 14px;
}

/* Balance Container */
.balance-container {
  position: relative;
}

.balance-section {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  padding: 10px 20px;
  background: linear-gradient(135deg, rgba(34, 197, 94, 0.08) 0%, rgba(59, 130, 246, 0.08) 100%);
  border: 1px solid rgba(34, 197, 94, 0.2);
  border-radius: 12px;
  min-width: 180px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.balance-section:hover {
  border-color: rgba(34, 197, 94, 0.4);
  background: linear-gradient(135deg, rgba(34, 197, 94, 0.12) 0%, rgba(59, 130, 246, 0.12) 100%);
}

.balance-header {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 4px;
}

.live-dot {
  width: 6px;
  height: 6px;
  background: #22c55e;
  border-radius: 50%;
  animation: pulse-dot 2s ease-in-out infinite;
  box-shadow: 0 0 8px rgba(34, 197, 94, 0.6);
}

@keyframes pulse-dot {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.6; transform: scale(0.9); }
}

.balance-label {
  font-size: 10px;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 1px;
  font-weight: 600;
}

.balance-main {
  display: flex;
  align-items: baseline;
  gap: 2px;
}

.balance-currency {
  font-size: 14px;
  color: #94a3b8;
  font-weight: 500;
}

.balance-value {
  font-size: 20px;
  font-weight: 700;
  color: #ffffff;
  font-variant-numeric: tabular-nums;
  transition: all 0.3s ease;
}

.balance-value.value-up {
  color: #22c55e;
  animation: flash-up 0.6s ease;
}

.balance-value.value-down {
  color: #ef4444;
  animation: flash-down 0.6s ease;
}

@keyframes flash-up {
  0% { background: rgba(34, 197, 94, 0.3); }
  100% { background: transparent; }
}

@keyframes flash-down {
  0% { background: rgba(239, 68, 68, 0.3); }
  100% { background: transparent; }
}

.unrealized-pl {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
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

.pl-icon {
  font-size: 8px;
}

/* Wallet Dropdown */
.wallet-dropdown {
  position: absolute;
  top: 100%;
  right: 0;
  margin-top: 8px;
  min-width: 280px;
  background: rgba(15, 20, 36, 0.98);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
  overflow: hidden;
  z-index: 1001;
}

.dropdown-header {
  padding: 14px 20px;
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 1px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  background: rgba(255, 255, 255, 0.02);
}

.wallet-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.04);
  transition: background 0.15s ease;
}

.wallet-row:last-child {
  border-bottom: none;
}

.wallet-row:hover {
  background: rgba(255, 255, 255, 0.03);
}

.wallet-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.wallet-currency {
  font-size: 14px;
  font-weight: 600;
  color: #ffffff;
}

.wallet-available {
  font-size: 11px;
  color: #64748b;
}

.wallet-values {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}

.wallet-balance {
  font-size: 14px;
  font-weight: 600;
  color: #ffffff;
  font-variant-numeric: tabular-nums;
}

.wallet-locked {
  font-size: 11px;
  color: #f59e0b;
}

/* Dropdown Animation */
.dropdown-enter-active,
.dropdown-leave-active {
  transition: all 0.2s ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
