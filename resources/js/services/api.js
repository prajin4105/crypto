import axios from 'axios'

const api = {
  // Markets
  getMarkets() {
    return axios.get('/api/markets')
  },

  // Orderbook
  getOrderbook(symbol, limit = 20) {
    return axios.get(`/api/orderbook/${symbol}`, { params: { limit } })
  },

  // Trades
  getTrades(symbol, limit = 50) {
    return axios.get(`/api/trades/${symbol}`, { params: { limit } })
  },

  // Orders
  getOrders() {
    return axios.get('/api/orders')
  },

  createOrder(data) {
    return axios.post('/api/orders', data)
  },

  cancelOrder(orderId) {
    return axios.post(`/api/orders/${orderId}/cancel`)
  },

  // Wallets
  getWallets() {
    return axios.get('/api/wallets')
  },

  depositWallet(data) {
    return axios.post('/api/wallets/deposit', data)
  },

  withdrawWallet(data) {
    return axios.post('/api/wallets/withdraw', data)
  },

  // Auth
  login(credentials) {
    return axios.post('/login', credentials)
  },

  logout() {
    return axios.post('/logout')
  },

  register(data) {
    return axios.post('/register', data)
  },

  getUser() {
    return axios.get('/user')
  },
}

export default api

