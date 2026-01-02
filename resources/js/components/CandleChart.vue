<template>
  <div class="chart-container">
    <!-- Timeframe Selector -->
    <div class="timeframe-selector">
      <button
        v-for="i in intervals"
        :key="i"
        :class="['timeframe-btn', interval === i ? 'active' : '']"
        @click="changeInterval(i)"
      >
        {{ i }}
      </button>
    </div>

    <!-- Chart Area -->
    <div class="chart-wrapper" ref="chartWrapper">
      <apexchart
        type="candlestick"
        height="600"
        :options="chartOptions"
        :series="candleSeries"
        ref="chart"
      />
      
      <!-- Volume Chart -->
      <div class="volume-chart-wrapper">
        <apexchart
          type="bar"
          height="120"
          :options="volumeOptions"
          :series="volumeSeries"
        />
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    symbol: { type: String, required: true },
    price: { type: Number, default: null },
    change: { type: Number, default: null },
  },
  data() {
    return {
      interval: '1h',
      intervals: ['1m', '5m', '15m', '1h', '4h', '1D'],
      candleSeries: [{ name: 'Price', data: [] }],
      volumeSeries: [{ name: 'Volume', data: [] }],
      socket: null,
      chartOptions: {
        chart: {
          type: 'candlestick',
          height: 600,
          toolbar: { show: false },
          background: 'transparent',
          zoom: {
            enabled: true,
            type: 'x',
            autoSelected: 'selection',
          },
          pan: {
            enabled: true,
          },
          animations: {
            enabled: true,
            speed: 300,
          },
        },
        theme: { 
          mode: 'dark',
        },
        plotOptions: {
          candlestick: {
            colors: {
              upward: '#22c55e',
              downward: '#ef4444',
            },
            wick: {
              useFillColor: true,
            },
          },
        },
        grid: {
          borderColor: '#1a1f3a',
          strokeDashArray: 0,
          xaxis: {
            lines: {
              show: true,
            },
          },
          yaxis: {
            lines: {
              show: true,
            },
          },
        },
        xaxis: {
          type: 'datetime',
          labels: {
            style: {
              colors: '#94a3b8',
              fontSize: '11px',
            },
            datetimeUTC: false,
          },
          axisBorder: {
            color: '#1a1f3a',
          },
          axisTicks: {
            color: '#1a1f3a',
          },
        },
        yaxis: {
          labels: {
            style: {
              colors: '#94a3b8',
              fontSize: '11px',
            },
            formatter: (val) => {
              return val.toFixed(2)
            },
          },
          opposite: true,
          tooltip: {
            enabled: true,
          },
        },
        tooltip: {
          theme: 'dark',
          x: {
            format: 'dd MMM yyyy HH:mm',
          },
          custom: ({ seriesIndex, dataPointIndex, w }) => {
            const o = w.globals.seriesCandleO[seriesIndex][dataPointIndex]
            const h = w.globals.seriesCandleH[seriesIndex][dataPointIndex]
            const l = w.globals.seriesCandleL[seriesIndex][dataPointIndex]
            const c = w.globals.seriesCandleC[seriesIndex][dataPointIndex]
            const v = this.volumeSeries[0].data[dataPointIndex]?.y || 0
            
            return `
              <div style="padding: 8px; background: #0a0e1a; border: 1px solid #1a1f3a;">
                <div style="margin-bottom: 4px;"><strong>Open:</strong> ${o.toFixed(2)}</div>
                <div style="margin-bottom: 4px;"><strong>High:</strong> ${h.toFixed(2)}</div>
                <div style="margin-bottom: 4px;"><strong>Low:</strong> ${l.toFixed(2)}</div>
                <div style="margin-bottom: 4px;"><strong>Close:</strong> ${c.toFixed(2)}</div>
                <div><strong>Volume:</strong> ${v.toLocaleString()}</div>
              </div>
            `
          },
        },
        crosshairs: {
          show: true,
          position: 'front',
          stroke: {
            color: '#3b82f6',
            width: 1,
            dashArray: 3,
          },
        },
      },
      volumeOptions: {
        chart: {
          type: 'bar',
          height: 120,
          toolbar: { show: false },
          background: 'transparent',
          sparkline: { enabled: false },
        },
        theme: { mode: 'dark' },
        plotOptions: {
          bar: {
            columnWidth: '80%',
            colors: {
              ranges: [{
                from: 0,
                to: 0,
                color: '#ef4444',
              }],
            },
          },
        },
        dataLabels: {
          enabled: false,
        },
        grid: {
          borderColor: '#1a1f3a',
          strokeDashArray: 0,
          xaxis: {
            lines: { show: false },
          },
          yaxis: {
            lines: { show: true },
          },
        },
        xaxis: {
          type: 'datetime',
          labels: {
            show: false,
          },
          axisBorder: {
            color: '#1a1f3a',
          },
          axisTicks: {
            color: '#1a1f3a',
          },
        },
        yaxis: {
          labels: {
            show: false,
          },
        },
        tooltip: {
          theme: 'dark',
          x: {
            format: 'dd MMM yyyy HH:mm',
          },
        },
      },
    }
  },
  mounted() {
    this.loadHistory()
    this.connectWS()
  },
  beforeUnmount() {
    if (this.socket) this.socket.close()
  },
  methods: {
    async loadHistory() {
      try {
        const res = await fetch(
          `https://api.binance.com/api/v3/klines?symbol=${this.symbol}&interval=${this.interval}&limit=200`
        )
        const data = await res.json()

        const candleData = []
        const volumeData = []

        data.forEach(d => {
          const timestamp = new Date(d[0])
          const open = +d[1]
          const high = +d[2]
          const low = +d[3]
          const close = +d[4]
          const volume = +d[5]
          const isUp = close >= open

          candleData.push({
            x: timestamp,
            y: [open, high, low, close],
          })

          volumeData.push({
            x: timestamp,
            y: volume,
            fillColor: isUp ? '#22c55e' : '#ef4444',
          })
        })

        this.candleSeries[0].data = candleData
        this.volumeSeries[0].data = volumeData
      } catch (error) {
        console.error('Error loading chart history:', error)
      }
    },

    connectWS() {
      if (this.socket) this.socket.close()

      this.socket = new WebSocket(
        `wss://stream.binance.com:9443/ws/${this.symbol.toLowerCase()}@kline_${this.interval}`
      )

      this.socket.onmessage = event => {
        const k = JSON.parse(event.data).k
        const timestamp = new Date(k.t)
        const open = +k.o
        const high = +k.h
        const low = +k.l
        const close = +k.c
        const volume = +k.v
        const isUp = close >= open
        const isClosed = k.x // x=true means candle is closed

        const candle = {
          x: timestamp,
          y: [open, high, low, close],
        }

        const volumeBar = {
          x: timestamp,
          y: volume,
          fillColor: isUp ? '#22c55e' : '#ef4444',
        }

        const candleData = [...this.candleSeries[0].data]
        const volumeData = [...this.volumeSeries[0].data]

        if (isClosed) {
          // New candle - add to end
          candleData.push(candle)
          volumeData.push(volumeBar)
          
          // Keep only last 200 candles
          if (candleData.length > 200) {
            candleData.shift()
            volumeData.shift()
          }
        } else {
          // Update last candle
          if (candleData.length > 0) {
            candleData[candleData.length - 1] = candle
            volumeData[volumeData.length - 1] = volumeBar
          }
        }

        this.candleSeries[0].data = candleData
        this.volumeSeries[0].data = volumeData
      }
    },

    changeInterval(i) {
      this.interval = i
      this.loadHistory()
      this.connectWS()
    },
  },
}
</script>

<style scoped>
.chart-container {
  display: flex;
  flex-direction: column;
  background: #0a0e1a;
  border: 1px solid #1a1f3a;
  padding: 16px;
}

.timeframe-selector {
  display: flex;
  gap: 4px;
  margin-bottom: 16px;
  padding-bottom: 16px;
  border-bottom: 1px solid #1a1f3a;
}

.timeframe-btn {
  padding: 8px 16px;
  background: transparent;
  border: 1px solid #1a1f3a;
  color: #94a3b8;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.timeframe-btn:hover {
  background: rgba(255, 255, 255, 0.05);
  border-color: #2a2f4a;
}

.timeframe-btn.active {
  background: #1a1f3a;
  border-color: #3b82f6;
  color: #3b82f6;
  box-shadow: 0 0 10px rgba(59, 130, 246, 0.2);
}

.chart-wrapper {
  position: relative;
}

.volume-chart-wrapper {
  margin-top: 8px;
  border-top: 1px solid #1a1f3a;
  padding-top: 8px;
}

/* Custom scrollbar for chart if needed */
.chart-container ::v-deep .apexcharts-canvas {
  cursor: crosshair !important;
}

.chart-container ::v-deep .apexcharts-zoom-icon,
.chart-container ::v-deep .apexcharts-zoom-in-icon,
.chart-container ::v-deep .apexcharts-zoom-out-icon,
.chart-container ::v-deep .apexcharts-pan-icon,
.chart-container ::v-deep .apexcharts-reset-icon,
.chart-container ::v-deep .apexcharts-menu-icon {
  display: none;
}
</style>
