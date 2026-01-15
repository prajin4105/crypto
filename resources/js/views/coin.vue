<template>
  <div class="coin-page">
    <!-- HEADER -->
    <div class="top-header">
      <div class="coin-info">
        <h1 class="coin-symbol">{{ symbol.replace('USDT', '') }}</h1>
        <span class="trading-pair">/ USDT</span>
        <div class="live-badge">
          <span class="live-dot"></span>
          <span class="live-text">LIVE</span>
        </div>
      </div>

      <div class="price-section">
        <div class="price-main">
          <span class="price-value" :class="{ positive: change >= 0, negative: change < 0 }">
            {{ price ? formatPrice(price) : '—' }}
          </span>
          <span class="price-unit">USDT</span>
        </div>
        <div class="price-change" v-if="change !== null">
          <span class="change-badge" :class="{ positive: change >= 0, negative: change < 0 }">
            <span class="change-icon">{{ change >= 0 ? '▲' : '▼' }}</span>
            {{ (change >= 0 ? '+' : '') + change.toFixed(2) }}%
          </span>
        </div>
      </div>

      <div class="time-section">
        <span class="time-label">IST</span>
        <span class="time-value">{{ currentTime }}</span>
      </div>
    </div>

    <!-- MAIN -->
    <div class="main-grid">
      <!-- CHART -->
      <div>
        <CandleChart :symbol="symbol" :price="price" :change="change" />
      </div>

      <!-- RIGHT PANEL -->
      <div class="right-panel">
        <!-- ORDER BOOK -->
        <div class="orderbook-section">
          <div class="section-header">
            <h3>Order Book</h3>
          </div>
          <div class="orderbook-content">
            <div class="orderbook-header">
              <span>Price (USDT)</span>
              <span>Amount</span>
              <span>Total</span>
            </div>
            
            <!-- SELL ORDERS (Asks) -->
            <div class="sell-orders">
              <div
                v-for="(o, i) in sellOrders"
                :key="'s'+i"
                class="order-row sell-row"
                @click="fillPrice(o.price)"
              >
                <span class="order-price">{{ formatPrice(o.price) }}</span>
                <span class="order-qty">{{ formatAmount(o.quantity) }}</span>
                <span class="order-total">{{ formatPrice(o.price * o.quantity) }}</span>
              </div>
            </div>

            <!-- PRICE DIVIDER -->
            <div class="price-divider">
              <span class="divider-price">{{ price ? formatPrice(price) : '—' }}</span>
            </div>

            <!-- BUY ORDERS (Bids) -->
            <div class="buy-orders">
              <div
                v-for="(o, i) in buyOrders"
                :key="'b'+i"
                class="order-row buy-row"
                @click="fillPrice(o.price)"
              >
                <span class="order-price">{{ formatPrice(o.price) }}</span>
                <span class="order-qty">{{ formatAmount(o.quantity) }}</span>
                <span class="order-total">{{ formatPrice(o.price * o.quantity) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- TRADES -->
        <div class="trades-section">
          <div class="section-header">
            <h3>Recent Trades</h3>
          </div>
          <div class="trades-content">
            <div
              v-for="(t, i) in recentTrades"
              :key="i"
              class="trade-row"
              :class="t.side === 'buy' ? 'trade-buy' : 'trade-sell'"
            >
              <span class="trade-price">{{ formatPrice(t.price) }}</span>
              <span class="trade-amount">{{ formatAmount(t.amount) }}</span>
              <span class="trade-time">{{ t.time }}</span>
            </div>
            <div v-if="recentTrades.length === 0" class="empty-state">
              No trades yet
            </div>
          </div>
        </div>

        <!-- BUY / SELL PANEL -->
        <div class="trading-panel">
          <div class="panel-tabs">
            <button
              class="tab-btn buy-tab"
              :class="{ active: activeTab === 'buy' }"
              @click="activeTab = 'buy'"
            >
              Buy
            </button>
            <button
              class="tab-btn sell-tab"
              :class="{ active: activeTab === 'sell' }"
              @click="activeTab = 'sell'"
            >
              Sell
            </button>
          </div>

          <div class="trading-form">
            <!-- Available Balance -->
            <div class="balance-info">
              <span class="balance-label">
                Available {{ activeTab === 'buy' ? 'USDT' : getBaseCurrency() }}
              </span>
              <span class="balance-value">
                {{ formatBalance(getAvailableBalance()) }}
              </span>
            </div>

            <!-- Price Input -->
            <div class="form-group">
              <label>Price (USDT)</label>
              <input
                type="number"
                step="0.00000001"
                v-model.number="tradePrice"
                placeholder="0.00"
                @input="calculateTotal"
              />
              <div class="quick-buttons">
                <button
                  v-for="percent in [25, 50, 75, 100]"
                  :key="percent"
                  class="quick-btn"
                  @click="setPricePercent(percent)"
                >
                  {{ percent }}%
                </button>
              </div>
            </div>

            <!-- Amount Input -->
            <div class="form-group">
              <label>Amount ({{ getBaseCurrency() }})</label>
              <input
                type="number"
                step="0.00000001"
                v-model.number="tradeAmount"
                placeholder="0.00"
                @input="calculateTotal"
              />
            </div>

            <!-- Total Display -->
            <div class="form-group">
              <label>Total (USDT)</label>
              <input
                type="text"
                :value="formatPrice(totalCost)"
                readonly
                class="readonly"
              />
            </div>

            <!-- Submit Button -->
            <button
              class="trade-btn"
              :class="activeTab === 'buy' ? 'buy-btn' : 'sell-btn'"
              @click="executeTrade"
              :disabled="!canTrade || loading"
            >
              <span v-if="loading">Processing...</span>
              <span v-else>{{ activeTab.toUpperCase() }} {{ getBaseCurrency() }}</span>
            </button>

            <!-- Error Message -->
            <div v-if="error" class="error-message">
              {{ error }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/services/api'
import CandleChart from '@/components/CandleChart.vue'

export default {
  name: 'Coin',
  components: { CandleChart },

  data() {
    return {
      symbol: this.$route.params.symbol,

      price: null,
      change: null,
      high: null,
      low: null,
      volume: null,

      currentTime: '',
      clock: null,
      orderbookInterval: null,
      tradesInterval: null,
      priceSocket: null,

      activeTab: 'buy',
      tradePrice: null,
      tradeAmount: null,
      totalCost: 0,
      loading: false,
      error: null,

      buyOrders: [],
      sellOrders: [],
      recentTrades: [],

      wallets: [],
      baseCurrency: '',
      quoteCurrency: 'USDT',
    }
  },

  mounted() {
    this.start()
  },

  beforeUnmount() {
    this.stop()
  },

    watch: {
      '$route.params.symbol'(s) {
        this.symbol = s
        this.baseCurrency = s.replace('USDT', '')
        this.stop()
        // Reset price when symbol changes
        this.price = null
        this.change = null
        this.tradePrice = null
        this.start()
      },
      activeTab() {
        this.error = null
        this.calculateTotal()
        this.fetchWallets()
      },
    },

  computed: {
    canTrade() {
      if (!this.tradePrice || !this.tradeAmount) return false
      if (this.tradePrice <= 0 || this.tradeAmount <= 0) return false
      if (this.loading) return false
      
      // Check balance
      const available = this.getAvailableBalance()
      const cost = this.totalCost
      
      if (this.activeTab === 'buy') {
        return cost <= available
      } else {
        return this.tradeAmount <= available
      }
    },
  },

  methods: {
    /* ---------- CORE ---------- */

    start() {
      this.fetchOrderbook()
      this.fetchTrades()
      this.fetchWallets()
      this.updateTime()
      this.connectPriceWebSocket()
      
      this.clock = setInterval(this.updateTime, 1000)
      this.orderbookInterval = setInterval(this.fetchOrderbook, 2000)
      this.tradesInterval = setInterval(this.fetchTrades, 3000)
    },

    stop() {
      clearInterval(this.clock)
      clearInterval(this.orderbookInterval)
      clearInterval(this.tradesInterval)
      this.disconnectPriceWebSocket()
    },

    /* ---------- LIVE PRICE WEBSOCKET ---------- */

    connectPriceWebSocket() {
      if (!this.symbol) return

      const symbolLower = this.symbol.toLowerCase()
      const wsUrl = `wss://stream.binance.com:9443/ws/${symbolLower}@ticker`

      try {
        this.priceSocket = new WebSocket(wsUrl)

        this.priceSocket.onopen = () => {
          console.log('Price WebSocket connected for', this.symbol)
        }

        this.priceSocket.onmessage = (event) => {
          try {
            const data = JSON.parse(event.data)
            
            if (data.c) { // Current price
              const newPrice = parseFloat(data.c)
              const priceChange = parseFloat(data.P) // 24h price change percent
              
              if (!isNaN(newPrice) && newPrice > 0) {
                // Calculate change from previous price
                if (this.price && this.price !== newPrice) {
                  this.change = ((newPrice - this.price) / this.price) * 100
                } else if (priceChange !== null && !isNaN(priceChange)) {
                  this.change = priceChange
                }
                
                this.price = newPrice
                
                // Update trade price if not manually set
                if (!this.tradePrice || this.tradePrice === this.price) {
                  this.tradePrice = newPrice
                }
                
                // Update other stats
                if (data.h) this.high = parseFloat(data.h)
                if (data.l) this.low = parseFloat(data.l)
                if (data.v) this.volume = parseFloat(data.v)
                
                this.calculateTotal()
              }
            }
          } catch (e) {
            console.error('Error parsing price WebSocket message:', e)
          }
        }

        this.priceSocket.onerror = (err) => {
          console.error('Price WebSocket error:', err)
        }

        this.priceSocket.onclose = () => {
          // Reconnect after 3 seconds
          setTimeout(() => {
            if (this.symbol) {
              this.connectPriceWebSocket()
            }
          }, 3000)
        }
      } catch (err) {
        console.error('Failed to connect price WebSocket:', err)
      }
    },

    disconnectPriceWebSocket() {
      if (this.priceSocket) {
        try {
          this.priceSocket.close()
        } catch (e) {
          // Ignore errors
        }
        this.priceSocket = null
      }
    },

    /* ---------- ORDER BOOK ---------- */

    async fetchOrderbook() {
      try {
        const res = await api.getOrderbook(this.symbol, 20)
        const data = res.data

        if (data && Array.isArray(data.bids) && Array.isArray(data.asks)) {
          this.sellOrders = data.asks.slice(0, 10).map(o => ({
            price: Number(o.price || 0),
            quantity: Number(o.amount || 0),
          }))

          this.buyOrders = data.bids.slice(0, 10).map(o => ({
            price: Number(o.price || 0),
            quantity: Number(o.amount || 0),
          }))

          // Update price from best bid/ask (fallback if WebSocket not connected)
          if (data.bids.length > 0 && data.asks.length > 0 && !this.price) {
            const bestBid = Number(data.bids[0].price)
            const bestAsk = Number(data.asks[0].price)
            const midPrice = (bestBid + bestAsk) / 2
            this.price = midPrice
            this.tradePrice = midPrice
            this.calculateTotal()
          }
        }
      } catch (e) {
        console.error('Failed to fetch orderbook', e)
      }
    },

    /* ---------- TRADES ---------- */

    async fetchTrades() {
      try {
        const res = await api.getTrades(this.symbol, 20)
        const trades = Array.isArray(res.data) ? res.data : []

        this.recentTrades = trades.slice(0, 20).map(t => ({
          price: Number(t.price || 0),
          amount: Number(t.amount || 0),
          side: t.side || 'buy',
          time: t.time ? new Date(t.time).toLocaleTimeString('en-GB') : new Date().toLocaleTimeString('en-GB'),
        }))
      } catch (e) {
        console.error('Failed to fetch trades', e)
      }
    },

    /* ---------- TIME ---------- */

    updateTime() {
      const ist = new Date(
        new Date().toLocaleString('en-US', { timeZone: 'Asia/Kolkata' })
      )

      this.currentTime = ist.toLocaleTimeString('en-GB')
    },

    /* ---------- WALLETS ---------- */

    async fetchWallets() {
      try {
        const res = await api.getWallets()
        this.wallets = Array.isArray(res.data) ? res.data : []
        
        // Extract base currency from symbol (e.g., BTCUSDT -> BTC)
        this.baseCurrency = this.symbol.replace('USDT', '')
      } catch (e) {
        console.error('Failed to fetch wallets', e)
        this.wallets = []
      }
    },

    getAvailableBalance() {
      if (this.activeTab === 'buy') {
        const usdtWallet = this.wallets.find(w => w.currency === 'USDT')
        return usdtWallet ? usdtWallet.available : 0
      } else {
        const baseWallet = this.wallets.find(w => w.currency === this.baseCurrency)
        return baseWallet ? baseWallet.available : 0
      }
    },

    getBaseCurrency() {
      return this.baseCurrency || 'BTC'
    },

    /* ---------- TRADING HELPERS ---------- */

    fillPrice(price) {
      this.tradePrice = price
      this.calculateTotal()
    },

    setPricePercent(percent) {
      if (!this.price) return
      
      const available = this.getAvailableBalance()
      if (this.activeTab === 'buy') {
        const maxAmount = available / this.price
        this.tradeAmount = (maxAmount * percent / 100)
        this.tradePrice = this.price
      } else {
        this.tradeAmount = (available * percent / 100)
        this.tradePrice = this.price
      }
      this.calculateTotal()
    },

    calculateTotal() {
      if (this.tradePrice && this.tradeAmount) {
        this.totalCost = this.tradePrice * this.tradeAmount
      } else {
        this.totalCost = 0
      }
      this.error = null
    },

    /* ---------- BUY / SELL ---------- */

    async executeTrade() {
      if (!this.canTrade) {
        this.error = 'Please check your inputs and balance'
        return
      }

      this.loading = true
      this.error = null

      try {
        await api.createOrder({
          symbol: this.symbol,
          type: this.activeTab,
          price: this.tradePrice,
          amount: this.tradeAmount,
        })

        // Reset form
        this.tradeAmount = null
        this.tradePrice = this.price
        this.totalCost = 0
        this.error = null
        
        // Refresh data
        this.fetchOrderbook()
        this.fetchTrades()
        this.fetchWallets()
        
        // Trigger balance update in navbar
        window.dispatchEvent(new Event('balance-updated'))
      } catch (e) {
        const message = e.response?.data?.message || 
                       e.response?.data?.error || 
                       'Order failed. Please try again.'
        this.error = message
      } finally {
        this.loading = false
      }
    },

    /* ---------- FORMATTING ---------- */

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

    formatBalance(value) {
      if (!value || value === 0) return '0.00'
      return value.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 8
      })
    },
  },
}
</script>



<style scoped>
.coin-page {
  background: linear-gradient(135deg, #0a0e1a 0%, #0d1220 100%);
  min-height: 100vh;
  padding: 24px;
  color: #e5e7eb;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Top Header */
.top-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding: 20px 24px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.02);
}

.coin-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.coin-symbol {
  font-size: 32px;
  font-weight: 800;
  letter-spacing: 1px;
  margin: 0;
  background: linear-gradient(135deg, #ffffff 0%, #94a3b8 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.trading-pair {
  font-size: 18px;
  color: #64748b;
  font-weight: 500;
}

.live-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  background: rgba(34, 197, 94, 0.1);
  border: 1px solid rgba(34, 197, 94, 0.3);
  border-radius: 6px;
  margin-left: 12px;
}

.live-dot {
  width: 6px;
  height: 6px;
  background: #22c55e;
  border-radius: 50%;
  animation: pulse-live 2s ease-in-out infinite;
  box-shadow: 0 0 8px rgba(34, 197, 94, 0.6);
}

@keyframes pulse-live {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.6; transform: scale(0.8); }
}

.live-text {
  font-size: 10px;
  font-weight: 700;
  color: #22c55e;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.price-section {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
}

.price-main {
  display: flex;
  align-items: baseline;
  gap: 8px;
}

.price-value {
  font-size: 32px;
  font-weight: 700;
  color: #ffffff;
  font-variant-numeric: tabular-nums;
  transition: color 0.3s ease;
}

.price-value.positive {
  color: #22c55e;
}

.price-value.negative {
  color: #ef4444;
}

.price-unit {
  font-size: 16px;
  color: #64748b;
  font-weight: 500;
}

.price-change {
  display: flex;
  align-items: center;
}

.change-badge {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.change-badge.positive {
  background: rgba(34, 197, 94, 0.15);
  color: #22c55e;
}

.change-badge.negative {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
}

.change-icon {
  font-size: 10px;
}

.time-section {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}

.time-label {
  font-size: 10px;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.time-value {
  font-size: 14px;
  color: #94a3b8;
  font-weight: 500;
  font-variant-numeric: tabular-nums;
}

/* Main Grid */
.main-grid {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 16px;
}

.chart-container,
.orderbook-section,
.trades-section,
.trading-panel {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 16px;
}

.price-section {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
}

.price-main {
  display: flex;
  align-items: baseline;
  gap: 8px;
}

.price-value {
  font-size: 36px;
  font-weight: 700;
  color: #ffffff;
  letter-spacing: 0.5px;
  font-variant-numeric: tabular-nums;
}

.price-value.positive {
  color: #22c55e;
}

.price-value.negative {
  color: #ef4444;
}

.price-unit {
  font-size: 18px;
  color: #94a3b8;
  font-weight: 500;
}

.price-change {
  display: flex;
  align-items: center;
}

.change-value {
  font-size: 18px;
  font-weight: 600;
  padding: 4px 8px;
  font-variant-numeric: tabular-nums;
}

.positive { color: #22c55e; }
.negative { color: #ef4444; }

.order-row,
.trade-row {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  font-size: 12px;
  padding: 4px 0;
}

.buy-row .order-price { color: #22c55e; }
.sell-row .order-price { color: #ef4444; }

.trading-form input {
  width: 100%;
  padding: 10px;
  background: #0b0f1a;
  border: 1px solid #1f2937;
  color: white;
}

.trade-btn {
  width: 100%;
  padding: 12px;
  font-weight: bold;
  margin-top: 8px;
  cursor: pointer;
}

.buy-btn {
  background: #22c55e;
  color: black;
}

.sell-btn {
  background: #ef4444;
  color: white;
}

.live-dot {
  width: 8px;
  height: 8px;
  background: #22c55e;
  border-radius: 50%;
  animation: pulse 2s infinite;
  box-shadow: 0 0 8px #22c55e;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

.price-section {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
}

.price-main {
  display: flex;
  align-items: baseline;
  gap: 8px;
}

.price-value {
  font-size: 36px;
  font-weight: 700;
  color: #ffffff;
  letter-spacing: 0.5px;
}

.price-unit {
  font-size: 18px;
  color: #94a3b8;
  font-weight: 500;
}

.price-change {
  display: flex;
  align-items: center;
}

.change-value {
  font-size: 18px;
  font-weight: 600;
  padding: 4px 8px;
}

.change-value.positive {
  color: #22c55e;
}

.change-value.negative {
  color: #ef4444;
}

.timezone-info {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}

.timezone-label {
  font-size: 11px;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.current-time {
  font-size: 14px;
  color: #94a3b8;
  font-weight: 500;
  font-variant-numeric: tabular-nums;
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin-bottom: 20px;
}

.stat-card {
  border: 1px solid #1a1f3a;
  background: #0a0e1a;
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.stat-label {
  font-size: 12px;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
}

.stat-value {
  font-size: 18px;
  font-weight: 700;
  color: #ffffff;
  font-variant-numeric: tabular-nums;
}

/* Main Grid */
.main-grid {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 12px;
}

.chart-container {
  border: 1px solid #1a1f3a;
  background: #0a0e1a;
}

.right-panel {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* Order Book */
.orderbook-section {
  border: 1px solid #1a1f3a;
  background: #0a0e1a;
  display: flex;
  flex-direction: column;
  max-height: 400px;
}

.section-header {
  padding: 12px 16px;
  border-bottom: 1px solid #1a1f3a;
}

.section-header h3 {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: #ffffff;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.orderbook-content {
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  max-height: 360px;
}

.orderbook-header {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  padding: 8px 16px;
  font-size: 11px;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
  border-bottom: 1px solid #1a1f3a;
}

.sell-orders {
  display: flex;
  flex-direction: column;
}

.buy-orders {
  display: flex;
  flex-direction: column-reverse;
}

.order-row {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  padding: 6px 16px;
  font-size: 12px;
  font-variant-numeric: tabular-nums;
  cursor: pointer;
  transition: background 0.1s;
}

.order-row:hover {
  background: rgba(255, 255, 255, 0.02);
}

.sell-row .order-price {
  color: #ef4444;
}

.buy-row .order-price {
  color: #22c55e;
}

.order-price,
.order-qty,
.order-total {
  color: #e5e7eb;
  font-weight: 500;
}

.price-divider {
  padding: 12px 16px;
  border-top: 1px solid #1a1f3a;
  border-bottom: 1px solid #1a1f3a;
  background: rgba(26, 31, 58, 0.5);
  text-align: center;
}

.divider-price {
  font-size: 16px;
  font-weight: 700;
  color: #ffffff;
  font-variant-numeric: tabular-nums;
}

/* Trades Feed */
.trades-section {
  border: 1px solid #1a1f3a;
  background: #0a0e1a;
  display: flex;
  flex-direction: column;
  max-height: 300px;
}

.trades-content {
  overflow-y: auto;
  max-height: 260px;
}

.trade-row {
  display: grid;
  grid-template-columns: 1fr 1fr auto;
  padding: 6px 16px;
  font-size: 12px;
  font-variant-numeric: tabular-nums;
  border-bottom: 1px solid rgba(26, 31, 58, 0.5);
}

.trade-buy .trade-price {
  color: #22c55e;
}

.trade-sell .trade-price {
  color: #ef4444;
}

.trade-price {
  font-weight: 600;
}

.trade-amount {
  color: #e5e7eb;
}

.trade-time {
  color: #64748b;
  font-size: 11px;
}

/* Trading Panel */
.trading-panel {
  border: 1px solid #1a1f3a;
  background: #0a0e1a;
}

.panel-tabs {
  display: grid;
  grid-template-columns: 1fr 1fr;
  border-bottom: 1px solid #1a1f3a;
}

.tab-btn {
  padding: 14px;
  background: transparent;
  border: none;
  font-size: 14px;
  font-weight: 600;
  color: #64748b;
  cursor: pointer;
  transition: all 0.2s;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.tab-btn.active.buy-tab {
  background: rgba(34, 197, 94, 0.1);
  color: #22c55e;
  border-bottom: 2px solid #22c55e;
}

.tab-btn.active.sell-tab {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
  border-bottom: 2px solid #ef4444;
}

.trading-form {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-group label {
  font-size: 12px;
  color: #94a3b8;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.form-group input {
  padding: 12px;
  background: #0a0e1a;
  border: 1px solid #1a1f3a;
  color: #ffffff;
  font-size: 14px;
  font-weight: 500;
  font-variant-numeric: tabular-nums;
  transition: border-color 0.2s;
}

.form-group input:focus {
  outline: none;
  border-color: #3b82f6;
}

.form-group input.readonly {
  background: rgba(26, 31, 58, 0.5);
  color: #94a3b8;
  cursor: not-allowed;
}

.trade-btn {
  padding: 16px;
  font-size: 16px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
  margin-top: 8px;
}

.buy-btn {
  background: #22c55e;
  color: #000000;
  box-shadow: 0 0 20px rgba(34, 197, 94, 0.3);
}

.buy-btn:hover {
  background: #16a34a;
  box-shadow: 0 0 30px rgba(34, 197, 94, 0.5);
}

.sell-btn {
  background: #ef4444;
  color: #ffffff;
  box-shadow: 0 0 20px rgba(239, 68, 68, 0.3);
}

.sell-btn:hover {
  background: #dc2626;
  box-shadow: 0 0 30px rgba(239, 68, 68, 0.5);
}

/* Scrollbar Styling */
.orderbook-content::-webkit-scrollbar,
.trades-content::-webkit-scrollbar {
  width: 6px;
}

.orderbook-content::-webkit-scrollbar-track,
.trades-content::-webkit-scrollbar-track {
  background: #0a0e1a;
}

.orderbook-content::-webkit-scrollbar-thumb,
.trades-content::-webkit-scrollbar-thumb {
  background: #1a1f3a;
  border-radius: 0;
}

.orderbook-content::-webkit-scrollbar-thumb:hover,
.trades-content::-webkit-scrollbar-thumb:hover {
  background: #2a2f4a;
}

/* Responsive */
@media (max-width: 1400px) {
  .main-grid {
    grid-template-columns: 1fr 280px;
  }
}

@media (max-width: 1024px) {
  .main-grid {
    grid-template-columns: 1fr;
  }

  .right-panel {
    display: grid;
    grid-template-columns: 1fr 1fr;
  }

  .trading-panel {
    grid-column: 1 / -1;
  }
}

.trade-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.balance-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px;
  background: rgba(26, 31, 58, 0.5);
  border-radius: 6px;
  margin-bottom: 16px;
}

.balance-label {
  font-size: 12px;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.balance-value {
  font-size: 14px;
  font-weight: 600;
  color: #ffffff;
  font-variant-numeric: tabular-nums;
}

.quick-buttons {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 6px;
  margin-top: 8px;
}

.quick-btn {
  padding: 6px 8px;
  background: rgba(26, 31, 58, 0.5);
  border: 1px solid #1a1f3a;
  color: #94a3b8;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  border-radius: 4px;
  transition: all 0.2s;
}

.quick-btn:hover {
  background: rgba(26, 31, 58, 0.8);
  color: #ffffff;
  border-color: #3b82f6;
}

.error-message {
  padding: 12px;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  border-radius: 6px;
  color: #ef4444;
  font-size: 12px;
  text-align: center;
  margin-top: 12px;
}

.empty-state {
  padding: 20px;
  text-align: center;
  color: #64748b;
  font-size: 12px;
}
</style>
