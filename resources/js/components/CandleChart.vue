<template>
  <div class="chart-container">
    <!-- Chart Header with Stats -->
    <div class="chart-header">
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
      
      <div class="chart-stats" v-if="candleSeries[0].data.length > 0">
        <div class="stat-item">
          <span class="stat-label">O</span>
          <span class="stat-value">{{ formatPrice(lastOHLC.o) }}</span>
        </div>
        <div class="stat-item">
          <span class="stat-label">H</span>
          <span class="stat-value high">{{ formatPrice(lastOHLC.h) }}</span>
        </div>
        <div class="stat-item">
          <span class="stat-label">L</span>
          <span class="stat-value low">{{ formatPrice(lastOHLC.l) }}</span>
        </div>
        <div class="stat-item">
          <span class="stat-label">C</span>
          <span class="stat-value" :class="{ up: lastOHLC.c >= lastOHLC.o, down: lastOHLC.c < lastOHLC.o }">
            {{ formatPrice(lastOHLC.c) }}
          </span>
        </div>
        <div class="stat-item volume">
          <span class="stat-label">Vol</span>
          <span class="stat-value">{{ formatVolume(lastVolume) }}</span>
        </div>
      </div>
    </div>

    <!-- Chart Area -->
    <div class="chart-wrapper" ref="chartWrapper">
      <apexchart
        type="candlestick"
        height="500"
        :options="chartOptions"
        :series="candleSeries"
        ref="chart"
      />
      
      <!-- Volume Chart -->
      <div class="volume-chart-wrapper">
        <apexchart
          type="bar"
          height="100"
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
          height: 500,
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
            speed: 200,
            animateGradually: {
              enabled: false,
            },
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
          borderColor: 'rgba(255, 255, 255, 0.04)',
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
              colors: '#64748b',
              fontSize: '11px',
              fontFamily: 'inherit',
            },
            datetimeUTC: false,
          },
          axisBorder: {
            color: 'rgba(255, 255, 255, 0.06)',
          },
          axisTicks: {
            color: 'rgba(255, 255, 255, 0.06)',
          },
          crosshairs: {
            stroke: {
              color: '#3b82f6',
              width: 1,
              dashArray: 4,
            },
          },
        },
        yaxis: {
          labels: {
            style: {
              colors: '#64748b',
              fontSize: '11px',
              fontFamily: 'inherit',
            },
            formatter: (val) => {
              if (val < 1) return val.toFixed(4)
              if (val < 100) return val.toFixed(2)
              return val.toLocaleString('en-US', { maximumFractionDigits: 2 })
            },
          },
          opposite: true,
          tooltip: {
            enabled: true,
          },
          crosshairs: {
            stroke: {
              color: '#3b82f6',
              width: 1,
              dashArray: 4,
            },
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
            const isUp = c >= o
            
            return `
              <div class="chart-tooltip">
                <div class="tooltip-row"><span class="tooltip-label">Open</span><span class="tooltip-value">${this.formatPrice(o)}</span></div>
                <div class="tooltip-row"><span class="tooltip-label">High</span><span class="tooltip-value highlight-high">${this.formatPrice(h)}</span></div>
                <div class="tooltip-row"><span class="tooltip-label">Low</span><span class="tooltip-value highlight-low">${this.formatPrice(l)}</span></div>
                <div class="tooltip-row"><span class="tooltip-label">Close</span><span class="tooltip-value ${isUp ? 'highlight-up' : 'highlight-down'}">${this.formatPrice(c)}</span></div>
                <div class="tooltip-row tooltip-divider"><span class="tooltip-label">Volume</span><span class="tooltip-value">${this.formatVolume(v)}</span></div>
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
            dashArray: 4,
          },
        },
      },
      volumeOptions: {
        chart: {
          type: 'bar',
          height: 100,
          toolbar: { show: false },
          background: 'transparent',
          sparkline: { enabled: false },
          animations: {
            enabled: false,
          },
        },
        theme: { mode: 'dark' },
        plotOptions: {
          bar: {
            columnWidth: '80%',
          },
        },
        dataLabels: {
          enabled: false,
        },
        grid: {
          borderColor: 'rgba(255, 255, 255, 0.04)',
          strokeDashArray: 0,
          xaxis: {
            lines: { show: false },
          },
          yaxis: {
            lines: { show: false },
          },
        },
        xaxis: {
          type: 'datetime',
          labels: {
            show: false,
          },
          axisBorder: {
            show: false,
          },
          axisTicks: {
            show: false,
          },
        },
        yaxis: {
          labels: {
            show: false,
          },
        },
        tooltip: {
          enabled: false,
        },
      },
    }
  },
  
  computed: {
    lastOHLC() {
      const data = this.candleSeries[0].data
      if (data.length === 0) return { o: 0, h: 0, l: 0, c: 0 }
      const last = data[data.length - 1]
      return {
        o: last.y[0],
        h: last.y[1],
        l: last.y[2],
        c: last.y[3],
      }
    },
    lastVolume() {
      const data = this.volumeSeries[0].data
      if (data.length === 0) return 0
      return data[data.length - 1].y
    },
  },
  
  mounted() {
    this.loadHistory()
    this.connectWS()
  },
  beforeUnmount() {
    if (this.socket) this.socket.close()
  },
  methods: {
    formatPrice(val) {
      if (!val) return '0.00'
      if (val < 0.01) return val.toFixed(6)
      if (val < 1) return val.toFixed(4)
      return val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    },
    
    formatVolume(val) {
      if (!val) return '0'
      if (val >= 1000000000) return (val / 1000000000).toFixed(2) + 'B'
      if (val >= 1000000) return (val / 1000000).toFixed(2) + 'M'
      if (val >= 1000) return (val / 1000).toFixed(2) + 'K'
      return val.toFixed(2)
    },
    
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
            fillColor: isUp ? 'rgba(34, 197, 94, 0.6)' : 'rgba(239, 68, 68, 0.6)',
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
        const isClosed = k.x

        const candle = {
          x: timestamp,
          y: [open, high, low, close],
        }

        const volumeBar = {
          x: timestamp,
          y: volume,
          fillColor: isUp ? 'rgba(34, 197, 94, 0.6)' : 'rgba(239, 68, 68, 0.6)',
        }

        const candleData = [...this.candleSeries[0].data]
        const volumeData = [...this.volumeSeries[0].data]

        if (isClosed) {
          candleData.push(candle)
          volumeData.push(volumeBar)
          
          if (candleData.length > 200) {
            candleData.shift()
            volumeData.shift()
          }
        } else {
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
  background: linear-gradient(180deg, #0a0e1a 0%, #0d1220 100%);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 16px;
  overflow: hidden;
}

/* Chart Header */
.chart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  background: rgba(255, 255, 255, 0.02);
}

.timeframe-selector {
  display: flex;
  gap: 4px;
}

.timeframe-btn {
  padding: 8px 14px;
  background: transparent;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  color: #64748b;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.timeframe-btn:hover {
  background: rgba(255, 255, 255, 0.05);
  color: #94a3b8;
  border-color: rgba(255, 255, 255, 0.15);
}

.timeframe-btn.active {
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(99, 102, 241, 0.15) 100%);
  border-color: rgba(59, 130, 246, 0.4);
  color: #3b82f6;
  box-shadow: 0 0 15px rgba(59, 130, 246, 0.2);
}

/* Chart Stats */
.chart-stats {
  display: flex;
  gap: 20px;
}

.stat-item {
  display: flex;
  align-items: center;
  gap: 6px;
}

.stat-label {
  font-size: 11px;
  color: #64748b;
  font-weight: 500;
  text-transform: uppercase;
}

.stat-value {
  font-size: 13px;
  color: #e5e7eb;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.stat-value.high {
  color: #22c55e;
}

.stat-value.low {
  color: #ef4444;
}

.stat-value.up {
  color: #22c55e;
}

.stat-value.down {
  color: #ef4444;
}

.stat-item.volume {
  padding-left: 20px;
  border-left: 1px solid rgba(255, 255, 255, 0.1);
}

/* Chart Wrapper */
.chart-wrapper {
  position: relative;
  padding: 16px;
}

.volume-chart-wrapper {
  margin-top: -20px;
  padding-top: 0;
}

/* Tooltip Styles - Applied globally via deep selector */
.chart-container :deep(.apexcharts-tooltip) {
  background: rgba(15, 20, 36, 0.95) !important;
  border: 1px solid rgba(255, 255, 255, 0.1) !important;
  border-radius: 10px !important;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5) !important;
  backdrop-filter: blur(10px);
}

.chart-container :deep(.chart-tooltip) {
  padding: 12px 16px;
  min-width: 140px;
}

.chart-container :deep(.tooltip-row) {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 4px 0;
}

.chart-container :deep(.tooltip-divider) {
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.chart-container :deep(.tooltip-label) {
  font-size: 11px;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.chart-container :deep(.tooltip-value) {
  font-size: 13px;
  color: #e5e7eb;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.chart-container :deep(.highlight-high) {
  color: #22c55e;
}

.chart-container :deep(.highlight-low) {
  color: #ef4444;
}

.chart-container :deep(.highlight-up) {
  color: #22c55e;
}

.chart-container :deep(.highlight-down) {
  color: #ef4444;
}

/* ApexCharts Customization */
.chart-container :deep(.apexcharts-canvas) {
  cursor: crosshair !important;
}

.chart-container :deep(.apexcharts-xaxistooltip),
.chart-container :deep(.apexcharts-yaxistooltip) {
  background: rgba(59, 130, 246, 0.9) !important;
  border: none !important;
  border-radius: 6px !important;
  color: #ffffff !important;
  font-size: 11px !important;
  font-weight: 500 !important;
}

.chart-container :deep(.apexcharts-xaxistooltip-bottom::before),
.chart-container :deep(.apexcharts-xaxistooltip-bottom::after) {
  border-bottom-color: rgba(59, 130, 246, 0.9) !important;
}

/* Hide default toolbar icons */
.chart-container :deep(.apexcharts-zoom-icon),
.chart-container :deep(.apexcharts-zoom-in-icon),
.chart-container :deep(.apexcharts-zoom-out-icon),
.chart-container :deep(.apexcharts-pan-icon),
.chart-container :deep(.apexcharts-reset-icon),
.chart-container :deep(.apexcharts-menu-icon) {
  display: none;
}
</style>
