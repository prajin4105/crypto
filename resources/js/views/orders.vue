<template>
  <div class="orders-page">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-content">
        <h1>My Orders</h1>
        <p class="subtitle">Track and manage your trading activity</p>
      </div>
      <button class="refresh-btn" @click="fetchOrders" :disabled="loading">
        <span class="refresh-icon" :class="{ spinning: loading }">↻</span>
        Refresh
      </button>
    </div>

    <!-- Summary Cards -->
    <div class="summary-cards">
      <div class="summary-card">
        <div class="card-icon open-icon">📊</div>
        <div class="card-content">
          <span class="card-label">Open Orders</span>
          <span class="card-value">{{ openOrdersCount }}</span>
        </div>
      </div>
      <div class="summary-card">
        <div class="card-icon filled-icon">✅</div>
        <div class="card-content">
          <span class="card-label">Filled</span>
          <span class="card-value">{{ filledOrdersCount }}</span>
        </div>
      </div>
      <div class="summary-card">
        <div class="card-icon cancelled-icon">❌</div>
        <div class="card-content">
          <span class="card-label">Cancelled</span>
          <span class="card-value">{{ cancelledOrdersCount }}</span>
        </div>
      </div>
      <div class="summary-card total-pl-card" :class="totalPLClass">
        <div class="card-icon pl-icon">{{ totalPL >= 0 ? '📈' : '📉' }}</div>
        <div class="card-content">
          <span class="card-label">Total P/L</span>
          <span class="card-value">{{ formatPLTotal() }}</span>
        </div>
      </div>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-section">
      <div class="filter-tabs">
        <button 
          v-for="f in filters" 
          :key="f.value"
          :class="['filter-tab', { active: filter === f.value }]"
          @click="filter = f.value"
        >
          <span class="filter-icon">{{ f.icon }}</span>
          {{ f.label }}
          <span class="filter-count">{{ getFilterCount(f.value) }}</span>
        </button>
      </div>
    </div>

    <!-- Orders Table -->
    <div class="orders-container">
      <div class="table-wrapper">
        <table class="orders-table">
          <thead>
            <tr>
              <th>Order</th>
              <th>Symbol</th>
              <th>Type</th>
              <th>Price</th>
              <th>Amount</th>
              <th>Progress</th>
              <th>Status</th>
              <th>P/L</th>
              <th>Action</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="o in filteredOrders" :key="o.id" class="order-row">
              <td class="order-id">
                <span class="id-badge">#{{ o.id }}</span>
                <span class="order-time">{{ o.created_at }}</span>
              </td>

              <td class="symbol-cell">
                <span class="symbol-name">{{ o.symbol.replace('USDT', '') }}</span>
                <span class="symbol-pair">/USDT</span>
              </td>

              <td>
                <span :class="['type-badge', o.type]">
                  <span class="type-icon">{{ o.type === 'buy' ? '↗' : '↘' }}</span>
                  {{ o.type.toUpperCase() }}
                </span>
              </td>

              <td class="price-cell">
                <span class="price-value">{{ formatPrice(o.price) }}</span>
                <span class="price-unit">USDT</span>
              </td>

              <td class="amount-cell">
                {{ formatAmount(o.amount) }}
              </td>

              <td class="progress-cell">
                <div class="progress-container">
                  <div class="progress-bar">
                    <div 
                      class="progress-fill" 
                      :class="o.type"
                      :style="{ width: getProgressPercent(o) + '%' }"
                    ></div>
                  </div>
                  <span class="progress-text">
                    {{ formatAmount(o.filled || (o.amount - o.remaining)) }} / {{ formatAmount(o.amount) }}
                  </span>
                </div>
              </td>

              <td>
                <span :class="['status-badge', o.status]">
                  <span class="status-dot"></span>
                  {{ formatStatus(o.status) }}
                </span>
              </td>

              <td :class="['pl-cell', getPLClass(o)]">
                <div class="pl-container">
                  <span class="pl-value">{{ formatPL(o) }}</span>
                  <span class="pl-percent" v-if="getPLPercent(o) !== null">
                    {{ getPLPercent(o) }}
                  </span>
                </div>
              </td>

              <td class="action-cell">
                <button
                  v-if="canCancel(o)"
                  class="cancel-btn"
                  @click="cancelOrder(o.id)"
                >
                  Cancel
                </button>
                <span v-else class="no-action">—</span>
              </td>
            </tr>

            <tr v-if="filteredOrders.length === 0">
              <td colspan="9" class="empty-state">
                <div class="empty-content">
                  <span class="empty-icon">📭</span>
                  <span class="empty-text">No orders found</span>
                  <span class="empty-hint">Your trading history will appear here</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
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
      filters: [
        { value: 'all', label: 'All', icon: '📋' },
        { value: 'open', label: 'Open', icon: '🔄' },
        { value: 'buy', label: 'Buy', icon: '🟢' },
        { value: 'sell', label: 'Sell', icon: '🔴' },
      ],
      loading: false,
      prices: {},
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
    this.priceUpdateInterval = setInterval(() => {
      this.fetchPrices()
    }, 5000)
    
    // Listen for balance/order updates
    window.addEventListener('balance-updated', this.fetchOrders)
  },

  beforeUnmount() {
    this.disconnectPriceWebSocket()
    if (this.priceUpdateInterval) {
      clearInterval(this.priceUpdateInterval)
    }
    window.removeEventListener('balance-updated', this.fetchOrders)
  },

  computed: {
    filteredOrders() {
      if (!Array.isArray(this.orders)) return []
      if (this.filter === 'all') return this.orders
      if (this.filter === 'open') return this.orders.filter(o => o && (o.status === 'open' || o.status === 'partial'))
      return this.orders.filter(o => o && o.type === this.filter)
    },
    
    openOrdersCount() {
      return this.orders.filter(o => o.status === 'open' || o.status === 'partial').length
    },
    
    filledOrdersCount() {
      return this.orders.filter(o => o.status === 'filled').length
    },
    
    cancelledOrdersCount() {
      return this.orders.filter(o => o.status === 'cancelled').length
    },
    
    totalPL() {
      let total = 0
      this.orders.forEach(o => {
        const { pl } = this.calculatePL(o)
        total += pl
      })
      return total
    },
    
    totalPLClass() {
      if (this.totalPL > 0) return 'positive'
      if (this.totalPL < 0) return 'negative'
      return 'neutral'
    },
  },

  methods: {
    async fetchOrders() {
      this.loading = true
      try {
        const res = await api.getOrders()
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
            ? new Date(o.created_at).toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
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
    
    getFilterCount(filterValue) {
      if (filterValue === 'all') return this.orders.length
      if (filterValue === 'open') return this.openOrdersCount
      return this.orders.filter(o => o.type === filterValue).length
    },
    
    getProgressPercent(order) {
      if (!order.amount || order.amount === 0) return 0
      const filled = order.filled || (order.amount - order.remaining)
      return Math.min(100, (filled / order.amount) * 100)
    },
    
    formatStatus(status) {
      const labels = {
        'open': 'Open',
        'partial': 'Partial',
        'filled': 'Filled',
        'cancelled': 'Cancelled',
      }
      return labels[status] || status
    },
    
    formatPLTotal() {
      if (this.totalPL === 0) return '$0.00'
      const sign = this.totalPL >= 0 ? '+' : ''
      return `${sign}$${Math.abs(this.totalPL).toFixed(2)}`
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
        window.dispatchEvent(new Event('balance-updated'))
      } catch (e) {
        const message = e.response?.data?.message || 'Unable to cancel order'
        alert(message)
      }
    },

    /* ---------- PRICE UPDATES ---------- */

    connectPriceWebSocket() {
      if (!this.orders.length || this.wsConnecting) return

      if (this.priceSocket) {
        try {
          this.priceSocket.close()
        } catch (e) {}
        this.priceSocket = null
      }

      if (this.wsReconnectTimeout) {
        clearTimeout(this.wsReconnectTimeout)
        this.wsReconnectTimeout = null
      }

      const symbols = [...new Set(
        this.orders
          .map(o => o.symbol?.toLowerCase())
          .filter(s => s && s.endsWith('usdt') && s.length >= 6 && s.length <= 12)
      )]
      
      if (symbols.length === 0) return

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

        ws.onerror = () => {
          this.wsConnecting = false
        }

        ws.onclose = (event) => {
          this.wsConnecting = false
          this.priceSocket = null
          if (event.code !== 1000 && this.orders.length > 0) {
            this.scheduleReconnect()
          }
        }

        setTimeout(() => {
          if (ws.readyState === WebSocket.CONNECTING) {
            ws.close()
            this.wsConnecting = false
            this.scheduleReconnect()
          }
        }, 10000)

      } catch (err) {
        this.wsConnecting = false
        this.scheduleReconnect()
      }
    },

    scheduleReconnect() {
      if (this.orders.length === 0) return
      if (this.reconnectAttempts >= this.maxReconnectAttempts) return

      this.reconnectAttempts++
      const delay = Math.min(3000 * this.reconnectAttempts, 30000)

      this.wsReconnectTimeout = setTimeout(() => {
        if (this.orders.length > 0 && !this.wsConnecting) {
          this.connectPriceWebSocket()
        }
      }, delay)
    },

    disconnectPriceWebSocket() {
      if (this.wsReconnectTimeout) {
        clearTimeout(this.wsReconnectTimeout)
        this.wsReconnectTimeout = null
      }

      if (this.priceSocket) {
        try {
          this.priceSocket.close(1000, 'Manual close')
        } catch (e) {}
        this.priceSocket = null
      }

      this.wsConnecting = false
      this.reconnectAttempts = 0
    },

    async fetchPrices() {
      const symbols = [...new Set(this.orders.map(o => o.symbol).filter(s => s))]
      for (const symbol of symbols) {
        try {
          const res = await api.getOrderbook(symbol, 1)
          if (res.data?.bids?.length > 0 && res.data?.asks?.length > 0) {
            const bestBid = parseFloat(res.data.bids[0].price)
            const bestAsk = parseFloat(res.data.asks[0].price)
            this.prices[symbol] = (bestBid + bestAsk) / 2
          }
        } catch (e) {}
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
          pl = (currentPrice - averageFillPrice) * filled - totalFees
          if (averageFillPrice > 0) {
            plPercent = ((currentPrice - averageFillPrice) / averageFillPrice) * 100
          }
        } else if (remaining > 0 && currentPrice > 0) {
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
          pl = (averageFillPrice - currentPrice) * filled - totalFees
          if (averageFillPrice > 0) {
            plPercent = ((averageFillPrice - currentPrice) / averageFillPrice) * 100
          }
        } else if (remaining > 0 && currentPrice > 0) {
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
        if (this.wsReconnectTimeout) {
          clearTimeout(this.wsReconnectTimeout)
        }
        
        this.wsReconnectTimeout = setTimeout(() => {
          if (this.priceSocket) {
            this.disconnectPriceWebSocket()
          }
          if (this.orders.length > 0 && !this.wsConnecting) {
            setTimeout(() => {
              this.connectPriceWebSocket()
            }, 500)
          }
        }, 1000)
      },
      deep: true
    }
  },
}
</script>

<style scoped>
.orders-page {
  min-height: 100vh;
  padding: 32px 48px;
  background: linear-gradient(135deg, #0a0e1a 0%, #0d1220 100%);
  color: #e5e7eb;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Page Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 32px;
}

.header-content h1 {
  font-size: 28px;
  font-weight: 700;
  margin: 0 0 4px 0;
  background: linear-gradient(135deg, #ffffff 0%, #94a3b8 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 0;
}

.refresh-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: rgba(59, 130, 246, 0.1);
  border: 1px solid rgba(59, 130, 246, 0.3);
  border-radius: 10px;
  color: #3b82f6;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
}

.refresh-btn:hover:not(:disabled) {
  background: rgba(59, 130, 246, 0.2);
  border-color: rgba(59, 130, 246, 0.5);
}

.refresh-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.refresh-icon {
  font-size: 16px;
  transition: transform 0.3s ease;
}

.refresh-icon.spinning {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Summary Cards */
.summary-cards {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 32px;
}

.summary-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px 24px;
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 16px;
  transition: all 0.2s ease;
}

.summary-card:hover {
  border-color: rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.04);
}

.card-icon {
  font-size: 28px;
}

.card-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.card-label {
  font-size: 12px;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.card-value {
  font-size: 24px;
  font-weight: 700;
  color: #ffffff;
  font-variant-numeric: tabular-nums;
}

.total-pl-card.positive .card-value {
  color: #22c55e;
}

.total-pl-card.negative .card-value {
  color: #ef4444;
}

/* Filter Section */
.filter-section {
  margin-bottom: 24px;
}

.filter-tabs {
  display: flex;
  gap: 8px;
}

.filter-tab {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: transparent;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 10px;
  color: #94a3b8;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
}

.filter-tab:hover {
  background: rgba(255, 255, 255, 0.05);
  border-color: rgba(255, 255, 255, 0.2);
}

.filter-tab.active {
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(99, 102, 241, 0.15) 100%);
  border-color: rgba(59, 130, 246, 0.4);
  color: #3b82f6;
  box-shadow: 0 0 20px rgba(59, 130, 246, 0.15);
}

.filter-icon {
  font-size: 14px;
}

.filter-count {
  padding: 2px 8px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 6px;
  font-size: 12px;
}

.filter-tab.active .filter-count {
  background: rgba(59, 130, 246, 0.3);
}

/* Orders Table */
.orders-container {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 16px;
  overflow: hidden;
}

.table-wrapper {
  overflow-x: auto;
}

.orders-table {
  width: 100%;
  border-collapse: collapse;
}

.orders-table th {
  padding: 16px 20px;
  text-align: left;
  font-size: 11px;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  background: rgba(255, 255, 255, 0.02);
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.orders-table td {
  padding: 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.04);
  vertical-align: middle;
}

.order-row {
  transition: background 0.15s ease;
}

.order-row:hover {
  background: rgba(255, 255, 255, 0.02);
}

.order-row:last-child td {
  border-bottom: none;
}

/* Order ID Cell */
.order-id {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.id-badge {
  font-size: 14px;
  font-weight: 600;
  color: #ffffff;
}

.order-time {
  font-size: 11px;
  color: #64748b;
}

/* Symbol Cell */
.symbol-cell {
  font-weight: 600;
}

.symbol-name {
  font-size: 15px;
  color: #ffffff;
}

.symbol-pair {
  font-size: 12px;
  color: #64748b;
}

/* Type Badge */
.type-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
}

.type-badge.buy {
  background: rgba(34, 197, 94, 0.15);
  color: #22c55e;
  border: 1px solid rgba(34, 197, 94, 0.3);
}

.type-badge.sell {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.3);
}

.type-icon {
  font-size: 10px;
}

/* Price Cell */
.price-cell {
  font-variant-numeric: tabular-nums;
}

.price-value {
  font-size: 14px;
  font-weight: 600;
  color: #ffffff;
}

.price-unit {
  font-size: 11px;
  color: #64748b;
  margin-left: 4px;
}

/* Amount Cell */
.amount-cell {
  font-size: 14px;
  font-weight: 500;
  color: #e5e7eb;
  font-variant-numeric: tabular-nums;
}

/* Progress Cell */
.progress-cell {
  min-width: 140px;
}

.progress-container {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.progress-bar {
  height: 6px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 3px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  border-radius: 3px;
  transition: width 0.3s ease;
}

.progress-fill.buy {
  background: linear-gradient(90deg, #22c55e 0%, #16a34a 100%);
}

.progress-fill.sell {
  background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
}

.progress-text {
  font-size: 11px;
  color: #94a3b8;
  font-variant-numeric: tabular-nums;
}

/* Status Badge */
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 500;
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.status-badge.open {
  background: rgba(251, 191, 36, 0.15);
  color: #fbbf24;
}

.status-badge.open .status-dot {
  background: #fbbf24;
  animation: pulse-status 2s infinite;
}

.status-badge.partial {
  background: rgba(56, 189, 248, 0.15);
  color: #38bdf8;
}

.status-badge.partial .status-dot {
  background: #38bdf8;
  animation: pulse-status 2s infinite;
}

.status-badge.filled {
  background: rgba(34, 197, 94, 0.15);
  color: #22c55e;
}

.status-badge.filled .status-dot {
  background: #22c55e;
}

.status-badge.cancelled {
  background: rgba(156, 163, 175, 0.15);
  color: #9ca3af;
}

.status-badge.cancelled .status-dot {
  background: #9ca3af;
}

@keyframes pulse-status {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

/* P/L Cell */
.pl-cell {
  min-width: 100px;
}

.pl-container {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.pl-value {
  font-size: 14px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.pl-percent {
  font-size: 11px;
  opacity: 0.8;
}

.pl-profit .pl-value,
.pl-profit .pl-percent {
  color: #22c55e;
}

.pl-loss .pl-value,
.pl-loss .pl-percent {
  color: #ef4444;
}

.pl-neutral .pl-value {
  color: #94a3b8;
}

/* Action Cell */
.action-cell {
  text-align: center;
}

.cancel-btn {
  padding: 8px 16px;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  border-radius: 8px;
  color: #ef4444;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.cancel-btn:hover {
  background: rgba(239, 68, 68, 0.2);
  border-color: rgba(239, 68, 68, 0.5);
}

.no-action {
  color: #64748b;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 60px 20px !important;
}

.empty-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.empty-icon {
  font-size: 48px;
  margin-bottom: 8px;
}

.empty-text {
  font-size: 16px;
  color: #94a3b8;
  font-weight: 500;
}

.empty-hint {
  font-size: 13px;
  color: #64748b;
}

/* Responsive */
@media (max-width: 1200px) {
  .summary-cards {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .orders-page {
    padding: 20px;
  }
  
  .summary-cards {
    grid-template-columns: 1fr;
  }
  
  .filter-tabs {
    flex-wrap: wrap;
  }
}
</style>
