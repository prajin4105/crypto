<template>
  <div class="markets">
    <h1 class="markets-title">Live Markets</h1>

    <!-- Markets Table -->
    <div class="table-wrapper">
      <table class="markets-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Coin</th>
            <th>Price (USDT)</th>
            <th>24h %</th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="(coin, index) in coins"
            :key="coin.symbol"
            class="row"
            @click="goToMarket(coin.symbol)"
          >
            <td>{{ index + 1 }}</td>

            <td class="coin-cell">
              <span class="coin-name">{{ coin.name }}</span>
              <small>{{ coin.symbol.replace('USDT', '') }}</small>
            </td>

            <td>
              <span v-if="coin.price !== null">
                {{ coin.price.toLocaleString() }}
              </span>
              <span v-else>—</span>
            </td>

            <td :class="coin.change >= 0 ? 'positive' : 'negative'">
              <span v-if="coin.change !== null">
                {{ coin.change.toFixed(2) }}%
              </span>
              <span v-else>—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'Market',

  data() {
    return {
      coins: [],
      socket: null,
    }
  },

  mounted() {
    this.fetchMarkets()
  },

  beforeUnmount() {
    if (this.socket) {
      this.socket.close()
      this.socket = null
    }
  },

  methods: {
    async fetchMarkets() {
      try {
        const res = await axios.get('/api/markets')
        const data = Array.isArray(res.data) ? res.data : []

        this.coins = data.map(market => ({
          symbol: market.symbol || '',
          name: market.name || '',
          price: null,
          change: null,
        }))

        if (this.coins.length > 0) {
          this.connectSocket()
        }
      } catch (err) {
        console.error('Failed to fetch markets', err)
        this.coins = []
      }
    },

    connectSocket() {
      if (!this.coins.length) return

      const streams = this.coins
        .map(c => c.symbol.toLowerCase() + '@ticker')
        .join('/')

      this.socket = new WebSocket(
        `wss://stream.binance.com:9443/stream?streams=${streams}`
      )

      this.socket.onmessage = event => {
        const msg = JSON.parse(event.data)
        const data = msg.data

        const coin = this.coins.find(c => c.symbol === data.s)
        if (!coin) return

        coin.price = Number(data.c)
        coin.change = Number(data.P)
      }

      this.socket.onerror = err => {
        console.error('WebSocket error', err)
      }
    },

    goToMarket(symbol) {
      this.$router.push(`/coin/${symbol}`)
    },
  },
}
</script>

<style scoped>
.markets {
  background: #000;
  min-height: 100vh;
  padding: 40px 60px;
  color: #e5e7eb;
}

.markets-title {
  font-size: 40px;
  margin-bottom: 24px;
  letter-spacing: 2px;
}

.table-wrapper {
  overflow-x: auto;
}

.markets-table {
  width: 100%;
  border-collapse: collapse;
}

.markets-table th,
.markets-table td {
  padding: 14px;
  border-bottom: 1px solid #1f2933;
  font-size: 14px;
}

.row {
  cursor: pointer;
}

.row:hover {
  background: rgba(255, 255, 255, 0.04);
}

.coin-cell {
  display: flex;
  flex-direction: column;
}

.coin-name {
  font-weight: 600;
}

.coin-cell small {
  color: #94a3b8;
}

.positive {
  color: #22c55e;
}

.negative {
  color: #ef4444;
}

@media (max-width: 768px) {
  .markets {
    padding: 20px;
  }
}
</style>
