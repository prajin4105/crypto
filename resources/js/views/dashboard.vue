    <template>
    <div class="dashboard-page">
        <div class="hero-section">
        <h1 class="hero-title">Manage Your Crypto<br>Without the Drama</h1>
        <p class="hero-subtitle">Live prices. Secure trades. No false promises.</p>
        </div>

        <p v-if="loading" class="status-text">Loading…</p>

        <div v-else-if="!user" class="status-text error">
        Not authenticated
        </div>

        <div v-else class="dashboard-content">
        <!-- Total Balance Card -->
        <div class="total-balance-card">
            <div class="balance-header">
            <div>
                <div class="balance-title">Total Portfolio Value</div>
                <div class="total-amount">{{ currencySymbol }}{{ totalBalanceDisplay }}</div>
                <div class="balance-subtitle">Across all wallets</div>
            </div>
            <div class="currency-selector">
                <label class="currency-label">Display in:</label>
                <select v-model="displayCurrency" class="currency-select">
                <option value="USD">USD ($)</option>
                <option value="INR">INR (₹)</option>
                <option value="EUR">EUR (€)</option>
                <option value="GBP">GBP (£)</option>
                <option value="JPY">JPY (¥)</option>
                </select>
            </div>
            </div>
        </div>

        <!-- User Info Card -->
        <div class="info-card">
            <div class="card-header">
            <div class="icon-circle">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
                </svg>
            </div>
            <span class="card-label">Account</span>
            </div>
            <div class="info-content">
            <div class="info-item">
                <span class="info-label">Name</span>
                <span class="info-value">{{ user.name }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Email</span>
                <span class="info-value">{{ user.email }}</span>
            </div>
            </div>
        </div>

        <!-- Deposit Card -->
        <div class="deposit-card">
            <div class="card-header">
            <div class="icon-circle green">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <polyline points="19 12 12 19 5 12"/>
                </svg>
            </div>
            <span class="card-label">Add Funds</span>
            </div>

            <select v-model="deposit.currency" class="input">
            <option disabled value="">Select Currency</option>
            <option>BTC</option>
            <option>ETH</option>
            <option>USDT</option>
             <option>INR</option>
            </select>

            <input
            type="number"
            step="0.00000001"
            class="input"
            placeholder="Amount"
            v-model.number="deposit.amount"
            />

            <button @click="depositFunds" class="btn-primary">Deposit Funds</button>
        </div>

        <!-- Wallets Grid -->
        <div v-if="walletsWithBalance.length" class="wallets-section">
            <h2 class="section-title">Your Wallets</h2>

            <div class="wallets-grid">
            <div class="wallet-card" v-for="w in walletsWithBalance" :key="w.id" :class="getWalletClass(w.currency)">
                <div class="card-header">
                <div class="icon-circle" :class="getWalletClass(w.currency)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                    <line x1="1" y1="10" x2="23" y2="10"/>
                    </svg>
                </div>
                <span class="card-label">{{ w.currency }}</span>
                </div>

                <div class="wallet-balance">
                <div class="balance-label">Balance</div>
                <div class="balance-amount">{{ w.balance }}</div>
                </div>

                <input
                type="number"
                step="0.00000001"
                class="input"
                placeholder="Withdraw amount"
                v-model.number="withdrawAmounts[w.currency]"
                />

                <button class="btn-danger" @click="withdrawFunds(w.currency)">
                Withdraw
                </button>
            </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
            <line x1="1" y1="10" x2="23" y2="10"/>
            </svg>
            <h3>No Active Wallets</h3>
            <p>Deposit funds to get started with trading</p>
        </div>

        <!-- Status Messages -->
        <div v-if="error" class="alert alert-error">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="15" y1="9" x2="9" y2="15"/>
            <line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
            {{ error }}
        </div>

        <div v-if="success" class="alert alert-success">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
            <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            {{ success }}
        </div>
        </div>
    </div>
    </template>

    <script>
    import axios from 'axios'

    export default {
    name: 'Dashboard',

    data() {
        return {
        user: null,
        loading: true,
        error: '',
        success: '',
        deposit: {
            currency: '',
            amount: null,
        },
        withdrawAmounts: {},
        displayCurrency: 'USD',
        exchangeRates: {
            USD: 1,
            INR: 83.12,
            EUR: 0.92,
            GBP: 0.79,
            JPY: 149.50,
        },
        cryptoPrices: {
            BTC: 72180,
            ETH: 2471,
            USDT: 1,
            SOL: 180,
        },
        }
    },

    computed: {
        walletsWithBalance() {
        if (!this.user || !this.user.wallets) return []
        return this.user.wallets.filter(w => w.balance > 0)
        },

        totalBalanceUSD() {
        if (!this.user || !this.user.wallets) return 0
        return this.user.wallets.reduce((total, wallet) => {
            const price = this.cryptoPrices[wallet.currency] || 0
            return total + (wallet.balance * price)
        }, 0)
        },

        totalBalanceDisplay() {
        const usdAmount = this.totalBalanceUSD
        const convertedAmount = usdAmount * this.exchangeRates[this.displayCurrency]
        return convertedAmount.toFixed(2)
        },

        currencySymbol() {
        const symbols = {
            USD: '$',
            INR: '₹',
            EUR: '€',
            GBP: '£',
            JPY: '¥',
        }
        return symbols[this.displayCurrency] || '$'
        }
    },

    async mounted() {
        await this.fetchUser()
    },

    methods: {
        async fetchUser() {
        try {
            const res = await axios.get('/user')
            this.user = res.data
        } catch {
            this.$router.push('/login')
        } finally {
            this.loading = false
        }
        },

        getWalletClass(currency) {
        const colors = {
            BTC: 'orange',
            ETH: 'red',
            USDT: 'teal',
            SOL: 'purple'
        }
        return colors[currency] || 'blue'
        },

        async depositFunds() {
        this.error = ''
        this.success = ''

        if (!this.deposit.currency || !this.deposit.amount || this.deposit.amount <= 0) {
            this.error = 'Select currency and enter valid amount'
            return
        }

        try {
            await axios.post('/wallet/deposit', this.deposit)
            this.success = 'Deposit successful'
            this.deposit.amount = null
            await this.fetchUser()
        } catch (e) {
            this.error = e.response?.data?.message || 'Deposit failed'
        }
        },

        async withdrawFunds(currency) {
        this.error = ''
        this.success = ''

        const amount = this.withdrawAmounts[currency]

        if (!amount || amount <= 0) {
            this.error = 'Enter valid withdrawal amount'
            return
        }

        try {
            await axios.post('/wallet/withdraw', {
            currency,
            amount,
            })

            this.success = 'Withdrawal successful'
            this.withdrawAmounts[currency] = null
            await this.fetchUser()
        } catch (e) {
            this.error = e.response?.data?.message || 'Withdrawal failed'
        }
        },
    },
    }
    </script>

    <style scoped>
    * {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    }
    .app{
    font-family: 'Archivo Black', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;

    }

    .dashboard-page {
    min-height: 100vh;
    background: #000000;
    color: #ffffff;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    padding: 0;
    }

    .hero-section {
    padding: 60px 24px 40px;
    max-width: 1200px;
    margin: 0 auto;
    }

    .hero-title {
    font-size: 48px;
    font-weight: 800;
    line-height: 1.1;
    margin-bottom: 16px;
    letter-spacing: -1px;
    }

    .hero-subtitle {
    font-size: 18px;
    color: #94a3b8;
    font-weight: 400;
    }

    .dashboard-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px;
    }

    .status-text {
    text-align: center;
    padding: 40px;
    font-size: 18px;
    color: #64748b;
    }

    .status-text.error {
    color: #ef4444;
    }

    .section-title {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 24px;
    letter-spacing: -0.5px;
    }

    .info-card {
    background: #e34e4e;
    padding: 32px;
    margin-bottom: 24px;
    }

    .deposit-card {
    background: #244e46;
    padding: 32px;
    margin-bottom: 24px;
    }

    .card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
    }

    .icon-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #2563eb;
    display: flex;
    align-items: center;
    justify-content: center;
    }

    .icon-circle svg {
    width: 20px;
    height: 20px;
    color: #ffffff;
    }

    .icon-circle.green {
    background: #10b981;
    }

    .icon-circle.orange {
    background: #f59e0b;
    }

    .icon-circle.red {
    background: #ef4444;
    }

    .icon-circle.teal {
    background: #14b8a6;
    }

    .icon-circle.purple {
    background: #8b5cf6;
    }

    .icon-circle.blue {
    background: #3b82f6;
    }

    .card-label {
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #ffffff;
    }

    .info-content {
    display: flex;
    flex-direction: column;
    gap: 20px;
    color: #ffffff;
    }

    .info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 0;
    border-bottom: 1px solid #2a2a2a;
    }

    .info-item:last-child {
    border-bottom: none;
    }

    .info-label {
    font-size: 14px;
    color: #ffffff;
    font-weight: 500;
    }

    .info-value {
    font-size: 16px;
    font-weight: 600;
    color: #ffffff;
    }

    .input {
    width: 100%;
    padding: 16px;
    background: #0a0a0a;
    border: 2px solid #2a2a2a;
    color: #ffffff;
    font-size: 16px;
    margin-bottom: 16px;
    transition: all 0.3s;
    }

    .input:focus {
    outline: none;
    border-color: #2563eb;
    background: #000000;
    }

    .input::placeholder {
    color: #4b5563;
    }

    button {
    width: 100%;
    padding: 16px 24px;
    font-size: 16px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
    }

    .btn-primary {
    background: #2563eb;
    color: #ffffff;
    }

    .btn-primary:hover {
    background: #1d4ed8;
    transform: translateY(-2px);
    }

    .btn-danger {
    background: #ef4444;
    color: #ffffff;
    }

    .btn-danger:hover {
    background: #dc2626;
    transform: translateY(-2px);
    }

    .total-balance-card {
    background: #f5f1e8;
    padding: 40px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
    }

    .total-balance-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: rgba(0, 0, 0, 0.03);
    border-radius: 50%;
    }

    .balance-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    position: relative;
    z-index: 1;
    }

    .balance-title {
    font-size: 14px;
    color: #2d3436;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 12px;
    }

    .total-amount {
    font-size: 56px;
    font-weight: 900;
    color: #2d3436;
    margin-bottom: 8px;
    line-height: 1;
    font-family: 'Courier New', monospace;
    }

    .balance-subtitle {
    font-size: 14px;
    color: #636e72;
    }

    .currency-selector {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
    }

    .currency-label {
    font-size: 12px;
    color: #2d3436;
    text-transform: uppercase;
    letter-spacing: 1px;
    }

    .currency-select {
    padding: 12px 16px;
    background: rgba(45, 52, 54, 0.1);
    border: 2px solid rgba(45, 52, 54, 0.2);
    color: #2d3436;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    }

    .currency-select:hover {
    background: rgba(45, 52, 54, 0.15);
    border-color: rgba(45, 52, 54, 0.3);
    }

    .currency-select:focus {
    outline: none;
    border-color: #2d3436;
    }

    .currency-select option {
    background: #f5f1e8;
    color: #2d3436;
    }

    .empty-state {
    text-align: center;
    padding: 80px 24px;
    background: #1a1a1a;
    margin-top: 24px;
    }

    .empty-state svg {
    width: 64px;
    height: 64px;
    color: #4b5563;
    margin-bottom: 24px;
    }

    .empty-state h3 {
    font-size: 24px;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 8px;
    }

    .empty-state p {
    font-size: 16px;
    color: #64748b;
    }

    .wallets-section {
    margin-top: 40px;
    }

    .wallets-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
    }

    .wallet-card {
    padding: 32px;
    transition: transform 0.3s, box-shadow 0.3s;
    }

    .wallet-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
    }

    .wallet-card.orange {
    background: #f5f1e8;
    border-left: 4px solid #f59e0b;
    }

    .wallet-card.red {
    background: #d63031;
    border-left: 4px solid #ffffff;
    }

    .wallet-card.teal {
    background: #0d5c63;
    border-left: 4px solid #00b894;
    }

    .wallet-card.purple {
    background: #8b5cf6;
    border-left: 4px solid #ffffff;
    }

    .wallet-card.blue {
    background: #00b894;
    border-left: 4px solid #ffffff;
    }

    .wallet-balance {
    margin-bottom: 24px;
    padding: 20px 0;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }

    .balance-label {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.7);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 8px;
    }

    .balance-amount {
    font-size: 32px;
    font-weight: 800;
    color: #ffffff;
    font-family: 'Courier New', monospace;
    }

    /* Orange card text color */
    .wallet-card.orange .balance-label {
    color: #636e72;
    }

    .wallet-card.orange .balance-amount {
    color: #2d3436;
    }

    .wallet-card.orange .wallet-balance {
    border-top: 1px solid rgba(45, 52, 54, 0.1);
    border-bottom: 1px solid rgba(45, 52, 54, 0.1);
    }

    .alert {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    margin-top: 24px;
    font-weight: 600;
    }

    .alert svg {
    width: 24px;
    height: 24px;
    flex-shrink: 0;
    }

    .alert-error {
    background: rgba(239, 68, 68, 0.1);
    border: 2px solid #ef4444;
    color: #ef4444;
    }

    .alert-success {
    background: rgba(16, 185, 129, 0.1);
    border: 2px solid #10b981;
    color: #10b981;
    }

    @media (max-width: 768px) {
    .hero-title {
        font-size: 36px;
    }

    .hero-subtitle {
        font-size: 16px;
    }

    .info-card,
    .deposit-card,
    .wallet-card {
        padding: 24px;
    }

    .total-balance-card {
        padding: 32px 24px;
    }

    .balance-header {
        flex-direction: column;
        gap: 24px;
    }

    .currency-selector {
        align-items: flex-start;
        width: 100%;
    }

    .currency-select {
        width: 100%;
    }

    .total-amount {
        font-size: 42px;
    }

    .wallets-grid {
        grid-template-columns: 1fr;
    }

    .balance-amount {
        font-size: 28px;
    }
    }
    </style>
