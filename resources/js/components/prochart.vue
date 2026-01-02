<template>
  <div ref="chart" class="pro-chart"></div>
</template>

<script>
import { createChart } from 'lightweight-charts'

export default {
  props: {
    symbol: String,
    interval: { default: '1m' },
  },
  data() {
    return {
      chart: null,
      series: null,
      socket: null,
    }
  },
  async mounted() {
    this.initChart()
    await this.loadHistory()
    this.connectWS()
  },
  beforeUnmount() {
    if (this.socket) this.socket.close()
    if (this.chart) this.chart.remove()
  },
  methods: {
    initChart() {
      this.chart = createChart(this.$refs.chart, {
        height: 520,
        layout: {
          background: { color: '#0a0e1a' },
          textColor: '#cbd5e1',
        },
        grid: {
          vertLines: { color: '#1f2933' },
          horzLines: { color: '#1f2933' },
        },
        crosshair: { mode: 1 },
        timeScale: {
          timeVisible: true,
          secondsVisible: false,
        },
        rightPriceScale: {
          borderColor: '#1f2933',
        },
      })

      this.series = this.chart.addCandlestickSeries({
        upColor: '#22c55e',
        downColor: '#ef4444',
        borderUpColor: '#22c55e',
        borderDownColor: '#ef4444',
        wickUpColor: '#22c55e',
        wickDownColor: '#ef4444',
      })
    },

    async loadHistory() {
      const res = await fetch(
        `https://api.binance.com/api/v3/klines?symbol=${this.symbol}&interval=${this.interval}&limit=150`
      )
      const data = await res.json()

      this.series.setData(
        data.map(d => ({
          time: d[0] / 1000,
          open: +d[1],
          high: +d[2],
          low: +d[3],
          close: +d[4],
        }))
      )
    },

    connectWS() {
      this.socket = new WebSocket(
        `wss://stream.binance.com:9443/ws/${this.symbol.toLowerCase()}@kline_${this.interval}`
      )

      this.socket.onmessage = e => {
        const k = JSON.parse(e.data).k
        this.series.update({
          time: k.t / 1000,
          open: +k.o,
          high: +k.h,
          low: +k.l,
          close: +k.c,
        })
      }
    },
  },
}
</script>

<style scoped>
.pro-chart {
  width: 100%;
  border-radius: 12px;
  overflow: hidden;
}
</style>
