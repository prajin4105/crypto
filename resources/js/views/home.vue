<template>
  <div class="app">
    <!-- Import Navbar component here: <Navbar /> -->

    <!-- Hero Section -->


    <section class="hero">
      <div class="hero-content">
        <h2 class="hero-title">
          Buy, Sell & Trade Crypto<br />
          <span class="gradient-text">Without the Drama</span>
        </h2>
        <p class="hero-subtitle">Live prices. Secure trades. No fake promises.</p>
        <div class="buttons">
          <button class="btn-primary">Start Trading</button>
          <button class="btn-outline" onclick="window.location.href='/markets'">
  View Markets
</button>

        </div>
      </div>
    </section>

    <!-- Bento Grid Cards -->
    <section class="bento-grid">
      <!-- Card 1: Bitcoin -->
      <div class="bento-card card-cream">
        <div class="card-content">
          <div class="coin-header">
            <div class="coin-icon">₿</div>
            <div class="live-badge">
              <span class="live-dot"></span>
              LIVE
            </div>
          </div>
          <h3 class="coin-name">Bitcoin</h3>
          <div class="coin-price">
            <span v-if="prices.BTC">
              ₹{{ formatPrice(prices.BTC.price * 83) }}
            </span>
            <span v-else class="loading">Loading...</span>
          </div>
          <div class="coin-stats">
            <div class="stat-row">
              <span class="stat-label">24h Change</span>
              <span class="stat-value" :class="prices.BTC?.change >= 0 ? 'positive' : 'negative'">
                {{ prices.BTC ? (prices.BTC.change >= 0 ? '↑' : '↓') + ' ' + Math.abs(prices.BTC.change).toFixed(2) + '%' : '—' }}
              </span>
            </div>
            <div class="stat-row">
              <span class="stat-label">High</span>
              <span class="stat-value">{{ prices.BTC ? '₹' + formatPrice(prices.BTC.high * 83) : '—' }}</span>
            </div>
            <div class="stat-row">
              <span class="stat-label">Low</span>
              <span class="stat-value">{{ prices.BTC ? '₹' + formatPrice(prices.BTC.low * 83) : '—' }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Card 2: Ethereum -->
      <div class="bento-card card-red">
        <div class="card-content">
          <div class="coin-header">
            <div class="coin-icon eth-icon">Ξ</div>
            <div class="live-badge red-badge">
              <span class="live-dot"></span>
              LIVE
            </div>
          </div>
          <h3 class="coin-name white-text">Ethereum</h3>
          <div class="coin-price white-text">
            <span v-if="prices.ETH">
              ₹{{ formatPrice(prices.ETH.price * 83) }}
            </span>
            <span v-else class="loading">Loading...</span>
          </div>
          <div class="coin-stats">
            <div class="stat-row">
              <span class="stat-label white-text">24h Change</span>
              <span class="stat-value white-text" :class="prices.ETH?.change >= 0 ? 'positive-white' : 'negative-white'">
                {{ prices.ETH ? (prices.ETH.change >= 0 ? '↑' : '↓') + ' ' + Math.abs(prices.ETH.change).toFixed(2) + '%' : '—' }}
              </span>
            </div>
            <div class="stat-row">
              <span class="stat-label white-text">High</span>
              <span class="stat-value white-text">{{ prices.ETH ? '₹' + formatPrice(prices.ETH.high * 83) : '—' }}</span>
            </div>
            <div class="stat-row">
              <span class="stat-label white-text">Low</span>
              <span class="stat-value white-text">{{ prices.ETH ? '₹' + formatPrice(prices.ETH.low * 83) : '—' }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Card 3: BNB -->
      <div class="bento-card card-green">
        <div class="card-content">
          <div class="coin-header">
            <div class="coin-icon bnb-icon">◆</div>
            <div class="live-badge green-badge">
              <span class="live-dot"></span>
              LIVE
            </div>
          </div>
          <h3 class="coin-name white-text">Binance Coin</h3>
          <div class="coin-price white-text">
            <span v-if="prices.BNB">
              ₹{{ formatPrice(prices.BNB.price * 83) }}
            </span>
            <span v-else class="loading">Loading...</span>
          </div>
          <div class="coin-stats">
            <div class="stat-row">
              <span class="stat-label white-text">24h Change</span>
              <span class="stat-value white-text" :class="prices.BNB?.change >= 0 ? 'positive-white' : 'negative-white'">
                {{ prices.BNB ? (prices.BNB.change >= 0 ? '↑' : '↓') + ' ' + Math.abs(prices.BNB.change).toFixed(2) + '%' : '—' }}
              </span>
            </div>
            <div class="stat-row">
              <span class="stat-label white-text">Volume</span>
              <span class="stat-value white-text">{{ prices.BNB ? formatVolume(prices.BNB.volume) : '—' }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Card 4: Solana -->
      <div class="bento-card card-teal">
        <div class="card-content">
          <div class="coin-header">
            <div class="coin-icon sol-icon">◎</div>
            <div class="live-badge teal-badge">
              <span class="live-dot"></span>
              LIVE
            </div>
          </div>
          <h3 class="coin-name white-text">Solana</h3>
          <div class="coin-price white-text">
            <span v-if="prices.SOL">
              ₹{{ formatPrice(prices.SOL.price * 83) }}
            </span>
            <span v-else class="loading">Loading...</span>
          </div>
          <div class="coin-stats">
            <div class="stat-row">
              <span class="stat-label white-text">24h Change</span>
              <span class="stat-value white-text" :class="prices.SOL?.change >= 0 ? 'positive-white' : 'negative-white'">
                {{ prices.SOL ? (prices.SOL.change >= 0 ? '↑' : '↓') + ' ' + Math.abs(prices.SOL.change).toFixed(2) + '%' : '—' }}
              </span>
            </div>
            <div class="stat-row">
              <span class="stat-label white-text">Volume</span>
              <span class="stat-value white-text">{{ prices.SOL ? formatVolume(prices.SOL.volume) : '—' }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Card 5: Market Overview -->
      <div class="bento-card card-cream">
        <div class="card-content">
          <h3 class="card-title">Market Overview</h3>
          <div class="market-stats">
            <div class="market-stat">
              <div class="market-label">Total Market Cap</div>
              <div class="market-value">₹{{ calculateTotalMarketCap() }}</div>
            </div>
            <div class="market-stat">
              <div class="market-label">24h Volume</div>
              <div class="market-value">₹{{ calculateTotalVolume() }}</div>
            </div>
            <div class="market-stat">
              <div class="market-label">BTC Dominance</div>
              <div class="market-value">{{ btcDominance }}%</div>
            </div>
            <div class="market-stat">
              <div class="market-label">Active Markets</div>
              <div class="market-value">{{ activeMarkets }}+</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Card 6: Real-time Chart -->
      <div class="bento-card card-red">
        <div class="card-content">
          <h3 class="card-title white-text">Live Market Activity</h3>
          <div class="mini-chart">
            <div
              v-for="(bar, i) in chartData"
              :key="i"
              class="chart-bar"
              :style="{ height: bar + '%' }"
            ></div>
          </div>
          <div class="chart-info">
            <span class="chart-label white-text">Last 30 updates</span>
            <span class="chart-trend white-text" :class="chartTrend >= 0 ? 'positive-white' : 'negative-white'">
              {{ chartTrend >= 0 ? '↑' : '↓' }} {{ Math.abs(chartTrend).toFixed(1) }}%
            </span>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script>
// Import Navbar component
import Navbar from '@/components/Navbar.vue'

export default {
  name: 'Home',
  components: {
     Navbar
   },
  data() {
    return {
      prices: {
        BTC: null,
        ETH: null,
        BNB: null,
        SOL: null,
      },
      socket: null,
      chartData: Array(30).fill(0).map(() => 30 + Math.random() * 70),
      activeMarkets: 150,
      btcDominance: 48.5,
    }
  },
  computed: {
    chartTrend() {
      if (this.chartData.length < 2) return 0;
      const first = this.chartData[0];
      const last = this.chartData[this.chartData.length - 1];
      return ((last - first) / first) * 100;
    }
  },
  mounted() {
    this.connectWebSocket();
    this.updateChart();
  },
  beforeUnmount() {
    if (this.socket) this.socket.close();
  },
  methods: {
    connectWebSocket() {
      this.socket = new WebSocket('wss://stream.binance.com:9443/ws');

      this.socket.onopen = () => {
        this.socket.send(JSON.stringify({
          method: 'SUBSCRIBE',
          params: ['btcusdt@ticker', 'ethusdt@ticker', 'bnbusdt@ticker', 'solusdt@ticker'],
          id: 1,
        }));
      };

      this.socket.onmessage = (event) => {
        const d = JSON.parse(event.data);

        if (d.s === 'BTCUSDT') {
          this.prices.BTC = {
            price: Number(d.c),
            change: Number(d.P),
            high: Number(d.h),
            low: Number(d.l),
            volume: Number(d.v),
          };
        }

        if (d.s === 'ETHUSDT') {
          this.prices.ETH = {
            price: Number(d.c),
            change: Number(d.P),
            high: Number(d.h),
            low: Number(d.l),
            volume: Number(d.v),
          };
        }

        if (d.s === 'BNBUSDT') {
          this.prices.BNB = {
            price: Number(d.c),
            change: Number(d.P),
            high: Number(d.h),
            low: Number(d.l),
            volume: Number(d.v),
          };
        }

        if (d.s === 'SOLUSDT') {
          this.prices.SOL = {
            price: Number(d.c),
            change: Number(d.P),
            high: Number(d.h),
            low: Number(d.l),
            volume: Number(d.v),
          };
        }
      };

      this.socket.onerror = (error) => {
        console.error('WebSocket error:', error);
      };
    },

    formatPrice(price) {
      return price.toLocaleString('en-IN', { maximumFractionDigits: 0 });
    },

    formatVolume(volume) {
      if (volume >= 1000000) {
        return (volume / 1000000).toFixed(2) + 'M';
      } else if (volume >= 1000) {
        return (volume / 1000).toFixed(2) + 'K';
      }
      return volume.toFixed(2);
    },

    calculateTotalMarketCap() {
      let total = 0;
      if (this.prices.BTC) total += this.prices.BTC.price * 83 * 19500000;
      if (this.prices.ETH) total += this.prices.ETH.price * 83 * 120000000;
      return (total / 1000000000000).toFixed(2) + 'T';
    },

    calculateTotalVolume() {
      let total = 0;
      if (this.prices.BTC) total += this.prices.BTC.volume * this.prices.BTC.price * 83;
      if (this.prices.ETH) total += this.prices.ETH.volume * this.prices.ETH.price * 83;
      return (total / 1000000000).toFixed(2) + 'B';
    },

    updateChart() {
      setInterval(() => {
        this.chartData.shift();
        this.chartData.push(30 + Math.random() * 70);
      }, 2000);
    }
  },
}
</script>

<style scoped>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}
/* //home.vue */

.app {
  background: #000000;
  min-height: 100vh;
  font-family: 'Archivo Black', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;

  /* font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; */
  color: #e5e7eb;
  padding-bottom: 80px;
}

/* Navbar */
.nav {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px 60px;
  background: rgba(20, 20, 20, 0.8);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  position: sticky;
  top: 0;
  z-index: 100;
}

.logo {
  font-size: 28px;
  font-weight: 900;
  color: #ffffff;
  letter-spacing: -1px;
}

.links {
  display: flex;
  gap: 32px;
  align-items: center;
}

.nav-link {
  color: #cbd5e1;
  text-decoration: none;
  font-weight: 500;
  font-size: 15px;
  transition: all 0.3s ease;
}

.nav-link:hover {
  color: #ffffff;
}

.btn-login {
  padding: 10px 24px;
  background: #ffffff;
  color: #000000;
  text-decoration: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  transition: all 0.3s ease;
}

.btn-login:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(255, 255, 255, 0.3);
}

/* Hero Section */
.hero {
  max-width: 1400px;
  margin: 0 auto;
  padding: 80px 60px 60px;
}

.hero-content {
  max-width: 700px;
}

.hero-title {
  font-size: 56px;
  font-weight: 900;
  line-height: 1.1;
  margin-bottom: 20px;
  letter-spacing: -2px;
  color: #ffffff;
}

.gradient-text {
  color: #ffffff;
}

.hero-subtitle {
  font-size: 18px;
  color: #94a3b8;
  margin-bottom: 32px;
}

.buttons {
  display: flex;
  gap: 16px;
}

button {
  padding: 14px 32px;
  font-size: 15px;
  font-weight: 700;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
}

.btn-primary {
  background: #ffffff;
  color: #000000;
  border-radius: 0;
}

.btn-primary:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 25px rgba(255, 255, 255, 0.3);
}

.btn-outline {
  background: transparent;
  border: 2px solid rgba(255, 255, 255, 0.3);
  color: #ffffff;
    border-radius: 0;
}

.btn-outline:hover {
  background: rgba(255, 255, 255, 0.1);
  border-color: #ffffff;
}

/* Bento Grid */
.bento-grid {
  max-width: 1400px;
  margin: 0 auto;
  padding: 0 60px;
  display: grid;
  grid-template-columns: repeat(3,01fr);
  /* gap: 0px; */
}

/* Bento Cards */
.bento-card {
  border-radius: 0px;
  padding: 32px;
  position: relative;
  overflow: hidden;
  transition: all 0.4s ease;
  cursor: pointer;
}



.card-content {
  position: relative;
  z-index: 2;
  height: 100%;
  display: flex;
  flex-direction: column;
}

/* Card Colors - Matching your image */
.card-cream {
  background: #f5f1e8;
  color: #2d3436;
}

.card-red {
  background: #d63031;
  color: #ffffff;
}

.card-green {
  background: #00b894;
  color: #ffffff;
}

.card-teal {
  background: #0d5c63;
  color: #ffffff;
}

/* Coin Header */
.coin-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.coin-icon {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 26px;
  font-weight: 900;
  background: rgba(0, 0, 0, 0.1);
  color: #2d3436;
}

.eth-icon, .bnb-icon, .sol-icon {
  background: rgba(255, 255, 255, 0.2);
  color: #ffffff;
}

.live-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: rgba(0, 0, 0, 0.1);
  border-radius: 20px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 1px;
  color: #2d3436;
}

.red-badge, .green-badge, .teal-badge {
  background: rgba(255, 255, 255, 0.2);
  color: #ffffff;
}

.live-dot {
  width: 6px;
  height: 6px;
  background: #00b894;
  border-radius: 50%;
  animation: blink 2s ease-in-out infinite;
}

@keyframes blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.3; }
}

.coin-name {
  font-size: 22px;
  font-weight: 700;
  margin-bottom: 12px;
}

.white-text {
  color: #ffffff;
}

.coin-price {
  font-size: 32px;
  font-weight: 900;
  font-family: 'Courier New', monospace;
  margin-bottom: 20px;
}

.loading {
  font-size: 16px;
  opacity: 0.6;
}

.coin-stats {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: auto;
}

.stat-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 8px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

.card-red .stat-row,
.card-green .stat-row,
.card-teal .stat-row {
  border-bottom-color: rgba(255, 255, 255, 0.2);
}

.stat-label {
  font-size: 12px;
  opacity: 0.7;
  font-weight: 500;
}

.stat-value {
  font-size: 14px;
  font-weight: 700;
}

.positive {
  color: #00b894;
}

.negative {
  color: #d63031;
}

.positive-white {
  color: #ffffff;
  opacity: 1;
}

.negative-white {
  color: #ffffff;
  opacity: 0.8;
}

/* Market Overview Card */
.card-title {
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 24px;
  color: #2d3436;
}

.market-stats {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.market-stat {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.market-label {
  font-size: 12px;
  color: #636e72;
  font-weight: 500;
}

.market-value {
  font-size: 26px;
  font-weight: 900;
  color: #2d3436;
}

/* Chart Card */
.mini-chart {
  display: flex;
  gap: 2px;
  height: 180px;
  align-items: flex-end;
  margin: 20px 0;
}

.chart-bar {
  flex: 1;
  background: rgba(255, 255, 255, 0.8);
  border-radius: 2px 2px 0 0;
  transition: all 0.3s ease;
}

.chart-bar:hover {
  background: rgba(255, 255, 255, 1);
}

.chart-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: auto;
}

.chart-label {
  font-size: 11px;
  opacity: 0.7;
}

.chart-trend {
  font-size: 14px;
  font-weight: 700;
}

/* Responsive */
@media (max-width: 1200px) {
  .bento-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .bento-grid {
    grid-template-columns: 1fr;
    padding: 0 20px;
  }

  .nav {
    padding: 20px;
  }

  .hero {
    padding: 40px 20px;
  }

  .hero-title {
    font-size: 36px;
  }

  .buttons {
    flex-direction: column;
    width: 100%;
  }

  button {
    width: 100%;
  }

  .links {
    gap: 16px;
  }
}
</style>
