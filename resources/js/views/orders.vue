<template>
  <div class="orders-page">
    <h1>My Orders</h1>

    <!-- Filters -->
    <div class="filters">
      <button :class="{ active: filter === 'all' }" @click="filter = 'all'">
        All
      </button>
      <button :class="{ active: filter === 'buy' }" @click="filter = 'buy'">
        Buy
      </button>
      <button :class="{ active: filter === 'sell' }" @click="filter = 'sell'">
        Sell
      </button>
    </div>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Symbol</th>
          <th>Type</th>
          <th>Price</th>
          <th>Amount</th>
          <th>Filled</th>
          <th>Remaining</th>
          <th>Status</th>
          <th>P/L</th>
          <th>Action</th>
          <th>Time</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="o in filteredOrders" :key="o.id">
          <td>{{ o.id }}</td>

          <td>{{ o.symbol }}</td>

          <td :class="['type', o.type]">
            {{ o.type.toUpperCase() }}
          </td>

          <td>{{ formatPrice(o.price) }}</td>

          <td>{{ formatAmount(o.amount) }}</td>

          <td>{{ formatAmount(o.filled || (o.amount - o.remaining)) }}</td>

          <td>{{ formatAmount(o.remaining) }}</td>

          <td :class="['status', o.status]">
            {{ o.status }}
          </td>

          <td :class="['pl-cell', getPLClass(o)]">
            <div class="pl-value">
              {{ formatPL(o) }}
            </div>
            <div class="pl-percent" v-if="getPLPercent(o) !== null">
              {{ getPLPercent(o) }}
            </div>
          </td>

          <td>
            <button
              v-if="canCancel(o)"
              class="cancel-btn"
              @click="cancelOrder(o.id)"
            >
              Cancel
            </button>
            <span v-else>—</span>
          </td>

          <td>{{ o.created_at }}</td>
        </tr>

        <tr v-if="filteredOrders.length === 0">
          <td colspan="11" style="text-align:center; padding:20px;">
            No orders found
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
import api from '@/services/api'

export default {
  name: 'Orders',

  data() {
    return {
      orders: [],
      filter: 'all',
      loading: false,
      prices: {}, // Store current market prices
      priceSocket: null,
      priceUpdateInterval: null,
      wsConnecting: false,
      wsReconnectTimeout: null,
      reconnectAttempts: 0,
      maxReconnectAttempts: 5,
    }
  },

  mounted() {
    this.fetchOrders()
    this.connectPriceWebSocket()
    // Refresh prices every 5 seconds
    this.priceUpdateInterval = setInterval(() => {
      this.fetchPrices()
    }, 5000)
  },

  beforeUnmount() {
    this.disconnectPriceWebSocket()
    if (this.priceUpdateInterval) {
      clearInterval(this.priceUpdateInterval)
    }
  },

  computed: {
    filteredOrders() {
      if (!Array.isArray(this.orders)) return []
      if (this.filter === 'all') return this.orders
      return this.orders.filter(o => o && o.type === this.filter)
    },
  },

  methods: {
    async fetchOrders() {
      this.loading = true
      try {
        const res = await api.getOrders()

        // Normalize response
        const raw = Array.isArray(res.data) ? res.data : []

        this.orders = raw.map(o => ({
          id: o.id || 0,
          symbol: o.symbol || '—',
          type: (o.type || '').toLowerCase(),
          price: Number(o.price || 0),
          amount: Number(o.amount || 0),
          remaining: Number(o.remaining || o.remaining_amount || 0),
          filled: Number(o.filled || 0),
          status: o.status || 'unknown',
          created_at: o.created_at
            ? new Date(o.created_at).toLocaleString()
            : '—',
          average_fill_price: Number(o.average_fill_price || 0),
          total_fees: Number(o.total_fees || 0),
        }))
      } catch (e) {
        console.error('Failed to fetch orders', e)
        this.orders = []
        if (e.response?.status === 401) {
          alert('Please login to view orders')
        }
      } finally {
        this.loading = false
      }
    },

    canCancel(order) {
      if (!order || !order.status) return false
      return order.status === 'open' || order.status === 'partial'
    },

    async cancelOrder(id) {
      if (!confirm('Cancel this order?')) return

      try {
        await api.cancelOrder(id)
        this.fetchOrders()
        // Trigger balance update
        window.dispatchEvent(new Event('balance-updated'))
      } catch (e) {
        const message = e.response?.data?.message || 'Unable to cancel order'
        alert(message)
      }
    },

    /* ---------- PRICE UPDATES ---------- */

    connectPriceWebSocket() {
      if (!this.orders.length || this.wsConnecting) return

      // Close existing connection if any
      if (this.priceSocket) {
        try {
          this.priceSocket.close()
        } catch (e) {
          // Ignore errors
        }
        this.priceSocket = null
      }

      // Clear any pending reconnect
      if (this.wsReconnectTimeout) {
        clearTimeout(this.wsReconnectTimeout)
        this.wsReconnectTimeout = null
      }

      // Get unique symbols and validate
      const symbols = [...new Set(
        this.orders
          .map(o => o.symbol?.toLowerCase())
          .filter(s => s && s.endsWith('usdt') && s.length >= 6 && s.length <= 12)
      )]
      
      if (symbols.length === 0) return

      // Create streams
      const streams = symbols.map(s => `${s}@ticker`).join('/')
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
              }
            }
          } catch (e) {
            console.error('Error parsing price WebSocket message:', e)
          }
        }

        ws.onerror = (err) => {
          console.error('Price WebSocket error:', err)
          this.wsConnecting = false
        }

        ws.onclose = (event) => {
          this.wsConnecting = false
          this.priceSocket = null

          // Only reconnect if it wasn't a manual close
          if (event.code !== 1000 && this.orders.length > 0) {
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
        console.error('Failed to create price WebSocket:', err)
        this.wsConnecting = false
        this.scheduleReconnect()
      }
    },

    scheduleReconnect() {
      if (this.orders.length === 0) return
      if (this.reconnectAttempts >= this.maxReconnectAttempts) {
        console.warn('Max WebSocket reconnect attempts reached for orders')
        return
      }

      this.reconnectAttempts++
      const delay = Math.min(3000 * this.reconnectAttempts, 30000) // Exponential backoff, max 30s

      this.wsReconnectTimeout = setTimeout(() => {
        if (this.orders.length > 0 && !this.wsConnecting) {
          this.connectPriceWebSocket()
        }
      }, delay)
    },

    disconnectPriceWebSocket() {
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

      // Reset state
      this.wsConnecting = false
      this.reconnectAttempts = 0
    },

    async fetchPrices() {
      // Fallback: fetch prices via API if WebSocket fails
      const symbols = [...new Set(this.orders.map(o => o.symbol).filter(s => s))]
      for (const symbol of symbols) {
        try {
          const res = await api.getOrderbook(symbol, 1)
          if (res.data && res.data.bids && res.data.bids.length > 0 && res.data.asks && res.data.asks.length > 0) {
            const bestBid = parseFloat(res.data.bids[0].price)
            const bestAsk = parseFloat(res.data.asks[0].price)
            this.prices[symbol] = (bestBid + bestAsk) / 2
          }
        } catch (e) {
          // Ignore errors
        }
      }
    },

    /* ---------- P/L CALCULATIONS ---------- */

    calculatePL(order) {
      if (!order) return { pl: 0, plPercent: null, isRealized: false }

      const currentPrice = this.prices[order.symbol] || 0
      const orderPrice = order.price || 0
      const filled = order.filled || 0
      const remaining = order.remaining || 0
      const averageFillPrice = order.average_fill_price || 0
      const totalFees = order.total_fees || 0

      let pl = 0
      let plPercent = null
      const isRealized = order.status === 'filled'

      if (order.type === 'buy') {
        if (isRealized && averageFillPrice > 0) {
          // Realized P/L for filled buy orders
          // P/L = (current_price - average_fill_price) * filled_amount - fees
          pl = (currentPrice - averageFillPrice) * filled - totalFees
          if (averageFillPrice > 0) {
            plPercent = ((currentPrice - averageFillPrice) / averageFillPrice) * 100
          }
        } else if (remaining > 0 && currentPrice > 0) {
          // Unrealized P/L for open/partial buy orders
          // P/L = (current_price - order_price) * remaining_amount
          const unrealizedPL = (currentPrice - orderPrice) * remaining
          const realizedPL = filled > 0 && averageFillPrice > 0 
            ? (currentPrice - averageFillPrice) * filled - totalFees
            : 0
          pl = unrealizedPL + realizedPL
          if (orderPrice > 0) {
            plPercent = ((currentPrice - orderPrice) / orderPrice) * 100
          }
        }
      } else if (order.type === 'sell') {
        if (isRealized && averageFillPrice > 0) {
          // Realized P/L for filled sell orders
          // P/L = (average_fill_price - current_price) * filled_amount - fees
          pl = (averageFillPrice - currentPrice) * filled - totalFees
          if (averageFillPrice > 0) {
            plPercent = ((averageFillPrice - currentPrice) / averageFillPrice) * 100
          }
        } else if (remaining > 0 && currentPrice > 0) {
          // Unrealized P/L for open/partial sell orders
          // P/L = (order_price - current_price) * remaining_amount
          const unrealizedPL = (orderPrice - currentPrice) * remaining
          const realizedPL = filled > 0 && averageFillPrice > 0
            ? (averageFillPrice - currentPrice) * filled - totalFees
            : 0
          pl = unrealizedPL + realizedPL
          if (orderPrice > 0) {
            plPercent = ((orderPrice - currentPrice) / orderPrice) * 100
          }
        }
      }

      return { pl, plPercent, isRealized }
    },

    formatPL(order) {
      const { pl } = this.calculatePL(order)
      if (pl === 0) return '—'
      const sign = pl >= 0 ? '+' : ''
      return `${sign}$${Math.abs(pl).toFixed(2)}`
    },

    getPLPercent(order) {
      const { plPercent } = this.calculatePL(order)
      if (plPercent === null) return null
      const sign = plPercent >= 0 ? '+' : ''
      return `${sign}${plPercent.toFixed(2)}%`
    },

    getPLClass(order) {
      const { pl } = this.calculatePL(order)
      if (pl > 0) return 'pl-profit'
      if (pl < 0) return 'pl-loss'
      return 'pl-neutral'
    },

    formatPrice(value) {
      if (!value || value === 0) return '0.00'
      if (value < 0.01) return value.toFixed(8)
      if (value < 1) return value.toFixed(4)
      return value.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      })
    },

    formatAmount(value) {
      if (!value || value === 0) return '0.00'
      if (value < 0.0001) return value.toFixed(8)
      return value.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 8
      })
    },
  },

  watch: {
    orders: {
      handler() {
        // Debounce WebSocket reconnection when orders change
        if (this.wsReconnectTimeout) {
          clearTimeout(this.wsReconnectTimeout)
        }
        
        this.wsReconnectTimeout = setTimeout(() => {
          if (this.priceSocket) {
            this.disconnectPriceWebSocket()
          }
          if (this.orders.length > 0 && !this.wsConnecting) {
            // Small delay to ensure orders are set
            setTimeout(() => {
              this.connectPriceWebSocket()
            }, 500)
          }
        }, 1000) // Debounce by 1 second
      },
      deep: true
    }
  },
}
</script>

<style scoped>
.orders-page {
  padding: 20px;
  color: #e5e7eb;
}

.filters {
  margin-bottom: 12px;
}

.filters button {
  margin-right: 8px;
  padding: 6px 12px;
  cursor: pointer;
}

.filters .active {
  background: #2563eb;
  color: white;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th,
td {
  padding: 8px;
  border-bottom: 1px solid #1f2937;
  text-align: center;
}

.type.buy {
  color: #22c55e;
}

.type.sell {
  color: #ef4444;
}

.status.open {
  color: #eab308;
}

.status.partial {
  color: #38bdf8;
}

.status.filled {
  color: #22c55e;
}

.status.cancelled {
  color: #9ca3af;
}

.cancel-btn {
  background: #ef4444;
  border: none;
  padding: 4px 10px;
  color: white;
  cursor: pointer;
}

.pl-cell {
  text-align: center;
  min-width: 100px;
}

.pl-value {
  font-weight: 600;
  font-size: 14px;
  font-variant-numeric: tabular-nums;
}

.pl-percent {
  font-size: 11px;
  opacity: 0.8;
  margin-top: 2px;
}

.pl-profit {
  color: #22c55e;
}

.pl-loss {
  color: #ef4444;
}

.pl-neutral {
  color: #94a3b8;
}
</style>
