<template>
  <div class="login-page">
    <div class="login-container">
      <!-- Left: Login Form -->
      <div class="login-card">
        <div class="login-header">
          <h1 class="login-title">Login</h1>
          <p class="login-subtitle">Access your CryptoX trading account</p>
        </div>

        <form @submit.prevent="handleLogin" class="login-form">
          <div class="form-group">
            <label for="email">Email Address</label>
            <input
              id="email"
              type="email"
              v-model="form.email"
              placeholder="you@example.com"
              required
              autocomplete="email"
              :class="{ 'error': errors.email }"
            />
            <span v-if="errors.email" class="error-message">{{ errors.email }}</span>
          </div>

          <div class="form-group">
            <label for="password">Password</label>
            <div class="password-input-wrapper">
              <input
                id="password"
                :type="showPassword ? 'text' : 'password'"
                v-model="form.password"
                placeholder="Enter your password"
                required
                autocomplete="current-password"
                :class="{ 'error': errors.password }"
              />
              <button
                type="button"
                class="password-toggle"
                @click="showPassword = !showPassword"
              >
                {{ showPassword ? '👁️' : '👁️‍🗨️' }}
              </button>
            </div>
            <span v-if="errors.password" class="error-message">{{ errors.password }}</span>
          </div>

          <div class="form-options">
            <label class="checkbox-label">
              <input type="checkbox" v-model="form.remember" />
              <span>Remember me</span>
            </label>
            <a href="/forgot-password" class="forgot-link">Forgot password?</a>
          </div>

          <div v-if="errors.general" class="general-error">
            {{ errors.general }}
          </div>

          <button
            type="submit"
            class="btn-submit"
            :disabled="loading"
          >
            <span v-if="loading" class="btn-loader"></span>
            <span v-else>Login</span>
          </button>
        </form>

        <div class="login-footer">
          <span class="footer-text">New to CryptoX?</span>
          <router-link to="/register" class="footer-link">Create Account</router-link>
        </div>
      </div>

      <!-- Right: Info/Stats Card -->
      <div class="info-card">
        <div class="info-header">
          <h2>Welcome Back</h2>
          <p>Secure trading platform</p>
        </div>

        <div class="stats-grid">
          <div class="stat-item">
            <span class="stat-label">Active Users</span>
            <strong class="stat-value">12,458</strong>
          </div>
          <div class="stat-item">
            <span class="stat-label">24h Volume</span>
            <strong class="stat-value">$2.4B</strong>
          </div>
          <div class="stat-item">
            <span class="stat-label">Markets</span>
            <strong class="stat-value">150+</strong>
          </div>
        </div>

        <div class="security-features">
          <h3>Security Features</h3>
          <ul class="features-list">
            <li>🔒 Two-Factor Authentication</li>
            <li>🛡️ SSL Encrypted</li>
            <li>✅ Email Verification</li>
            <li>🔐 Cold Wallet Storage</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import axios from 'axios'

export default {
  name: 'Login',

  data() {
    return {
      form: {
        email: '',
        password: '',
        remember: false,
      },
      showPassword: false,
      loading: false,
      errors: {},
    }
  },

  methods: {
    async handleLogin() {
      this.errors = {}
      this.loading = true

      try {
        if (!this.form.email) {
          this.errors.email = 'Email is required'
        }
        if (!this.form.password) {
          this.errors.password = 'Password is required'
        }

        if (Object.keys(this.errors).length) {
          this.loading = false
          return
        }

        // CSRF cookie (required for session auth)
        await axios.get('/sanctum/csrf-cookie')

        // Login
        await axios.post('/login', {
          email: this.form.email,
          password: this.form.password,
          remember: this.form.remember,
        })

        // 🔥 TELL NAVBAR THAT AUTH CHANGED
        window.dispatchEvent(new Event('auth-updated'))

        // Redirect
        this.$router.push('/dashboard')

      } catch (err) {
        if (err.response?.data?.errors) {
          this.errors = err.response.data.errors
        } else {
          this.errors.general =
            err.response?.data?.message || 'Login failed'
        }
      } finally {
        this.loading = false
      }
    },
  },
}
</script>



<style scoped>
.login-page {
  min-height: 100vh;
  background: linear-gradient(135deg, #0a0e27 0%, #000000 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.login-container {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
  max-width: 1000px;
  width: 100%;
}

.login-card {
  border: 1px solid #1a1f3a;
  background: #0a0e1a;
  padding: 40px;
  display: flex;
  flex-direction: column;
}

.login-header {
  margin-bottom: 32px;
}

.login-title {
  font-size: 32px;
  font-weight: 700;
  color: #ffffff;
  margin: 0 0 8px 0;
  letter-spacing: 0.5px;
}

.login-subtitle {
  font-size: 14px;
  color: #94a3b8;
  margin: 0;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
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
  padding: 14px 16px;
  background: #0a0e1a;
  border: 1px solid #1a1f3a;
  color: #ffffff;
  font-size: 14px;
  font-variant-numeric: tabular-nums;
  transition: border-color 0.2s;
}

.form-group input:focus {
  outline: none;
  border-color: #3b82f6;
}

.form-group input.error {
  border-color: #ef4444;
}

.form-group input::placeholder {
  color: #64748b;
}

.password-input-wrapper {
  position: relative;
}

.password-toggle {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  font-size: 18px;
  padding: 4px;
  transition: color 0.2s;
}

.password-toggle:hover {
  color: #ffffff;
}

.error-message {
  font-size: 12px;
  color: #ef4444;
  margin-top: 4px;
}

.general-error {
  padding: 12px;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid #ef4444;
  color: #ef4444;
  font-size: 13px;
  border-radius: 0;
}

.form-options {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 4px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #94a3b8;
  cursor: pointer;
  text-transform: none;
  font-weight: normal;
}

.checkbox-label input[type="checkbox"] {
  width: 16px;
  height: 16px;
  cursor: pointer;
  accent-color: #22c55e;
}

.forgot-link {
  font-size: 13px;
  color: #3b82f6;
  text-decoration: none;
  transition: color 0.2s;
}

.forgot-link:hover {
  color: #60a5fa;
}

.btn-submit {
  width: 100%;
  padding: 16px;
  background: #22c55e;
  color: #000000;
  font-size: 16px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
  margin-top: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  box-shadow: 0 0 20px rgba(34, 197, 94, 0.3);
}

.btn-submit:hover:not(:disabled) {
  background: #16a34a;
  box-shadow: 0 0 30px rgba(34, 197, 94, 0.5);
}

.btn-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-loader {
  width: 18px;
  height: 18px;
  border: 2px solid rgba(0, 0, 0, 0.3);
  border-top-color: #000000;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.login-footer {
  margin-top: 24px;
  padding-top: 24px;
  border-top: 1px solid #1a1f3a;
  text-align: center;
}

.footer-text {
  font-size: 13px;
  color: #94a3b8;
}

.footer-link {
  margin-left: 8px;
  color: #22c55e;
  text-decoration: none;
  font-weight: 600;
  transition: color 0.2s;
}

.footer-link:hover {
  color: #16a34a;
}

/* Info Card */
.info-card {
  border: 1px solid #1a1f3a;
  background: #0a0e1a;
  padding: 40px;
  display: flex;
  flex-direction: column;
  gap: 32px;
}

.info-header h2 {
  font-size: 24px;
  font-weight: 700;
  color: #ffffff;
  margin: 0 0 8px 0;
}

.info-header p {
  font-size: 14px;
  color: #94a3b8;
  margin: 0;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.stat-item {
  border: 1px solid #1a1f3a;
  padding: 16px;
  background: rgba(26, 31, 58, 0.3);
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.stat-label {
  font-size: 11px;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
}

.stat-value {
  font-size: 20px;
  font-weight: 700;
  color: #ffffff;
  font-variant-numeric: tabular-nums;
}

.security-features h3 {
  font-size: 16px;
  font-weight: 600;
  color: #ffffff;
  margin: 0 0 16px 0;
}

.features-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.features-list li {
  font-size: 14px;
  color: #94a3b8;
  padding-left: 0;
}

/* Responsive */
@media (max-width: 968px) {
  .login-container {
    grid-template-columns: 1fr;
  }

  .info-card {
    order: -1;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .login-card,
  .info-card {
    padding: 24px;
  }

  .login-title {
    font-size: 24px;
  }
}
</style>
