<template>
  <div class="register-page">
    <div class="register-container">
      <!-- Left: Register Form -->
      <div class="register-card">
        <div class="register-header">
          <h1 class="register-title">Create Account</h1>
          <p class="register-subtitle">Join CryptoX trading platform</p>
        </div>

        <form @submit.prevent="handleRegister" class="register-form">
          <div class="form-group">
            <label for="name">Full Name</label>
            <input
              id="name"
              type="text"
              v-model="form.name"
              placeholder="John Doe"
              required
              autocomplete="name"
              :class="{ 'error': errors.name }"
            />
            <span v-if="errors.name" class="error-message">{{ errors.name }}</span>
          </div>

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
                placeholder="Minimum 8 characters"
                required
                autocomplete="new-password"
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

          <div class="form-group">
            <label for="password_confirmation">Confirm Password</label>
            <div class="password-input-wrapper">
              <input
                id="password_confirmation"
                :type="showConfirmPassword ? 'text' : 'password'"
                v-model="form.password_confirmation"
                placeholder="Re-enter your password"
                required
                autocomplete="new-password"
                :class="{ 'error': errors.password_confirmation }"
              />
              <button
                type="button"
                class="password-toggle"
                @click="showConfirmPassword = !showConfirmPassword"
              >
                {{ showConfirmPassword ? '👁️' : '👁️‍🗨️' }}
              </button>
            </div>
            <span v-if="errors.password_confirmation" class="error-message">{{ errors.password_confirmation }}</span>
          </div>

          <div class="form-options">
            <label class="checkbox-label">
              <input type="checkbox" v-model="form.remember" />
              <span>Remember me</span>
            </label>
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
            <span v-else>Create Account</span>
          </button>
        </form>

        <div class="register-footer">
          <span class="footer-text">Already have an account?</span>
          <router-link to="/login" class="footer-link">Login</router-link>
        </div>
      </div>

      <!-- Right: Info Card -->
      <div class="info-card">
        <div class="info-header">
          <h2>Start Trading</h2>
          <p>Join thousands of traders</p>
        </div>

        <div class="benefits-list">
          <div class="benefit-item">
            <span class="benefit-icon">💰</span>
            <div>
              <h3>Low Fees</h3>
              <p>Competitive trading fees starting from 0.1%</p>
            </div>
          </div>
          <div class="benefit-item">
            <span class="benefit-icon">⚡</span>
            <div>
              <h3>Fast Execution</h3>
              <p>Real-time order execution with minimal latency</p>
            </div>
          </div>
          <div class="benefit-item">
            <span class="benefit-icon">🔒</span>
            <div>
              <h3>Secure Platform</h3>
              <p>Bank-level security with 2FA and cold storage</p>
            </div>
          </div>
          <div class="benefit-item">
            <span class="benefit-icon">📊</span>
            <div>
              <h3>Advanced Charts</h3>
              <p>Professional trading tools and analytics</p>
            </div>
          </div>
        </div>

        <div class="security-note">
          <p>🔐 Your data is encrypted and secure</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'Register',
  data() {
    return {
      form: {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        remember: false,
      },
      showPassword: false,
      showConfirmPassword: false,
      loading: false,
      errors: {},
    }
  },
  methods: {
    async handleRegister() {
      // Reset errors
      this.errors = {}
      this.loading = true

      try {
        // Validate passwords match
        if (this.form.password !== this.form.password_confirmation) {
          this.errors.password_confirmation = 'Passwords do not match'
          this.loading = false
          return
        }

        // Validate minimum password length
        if (this.form.password.length < 8) {
          this.errors.password = 'Password must be at least 8 characters'
          this.loading = false
          return
        }

        const response = await fetch('/register', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          },
          credentials: 'same-origin',
          body: JSON.stringify({
            name: this.form.name,
            email: this.form.email,
            password: this.form.password,
            password_confirmation: this.form.password_confirmation,
            remember: this.form.remember,
          }),
        })

        const data = await response.json()

        if (!response.ok) {
          if (data.errors) {
            this.errors = data.errors
          } else {
            this.errors.general = data.message || 'Registration failed. Please try again.'
          }
          this.loading = false
          return
        }

        // Registration successful - redirect to dashboard
        this.$router.push('/dashboard')

      } catch (error) {
        console.error('Registration error:', error)
        this.errors.general = 'Network error. Please try again.'
        this.loading = false
      }
    },
  },
}
</script>

<style scoped>
.register-page {
  min-height: 100vh;
  background: linear-gradient(135deg, #0a0e27 0%, #000000 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.register-container {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
  max-width: 1000px;
  width: 100%;
}

.register-card {
  border: 1px solid #1a1f3a;
  background: #0a0e1a;
  padding: 40px;
  display: flex;
  flex-direction: column;
}

.register-header {
  margin-bottom: 32px;
}

.register-title {
  font-size: 32px;
  font-weight: 700;
  color: #ffffff;
  margin: 0 0 8px 0;
  letter-spacing: 0.5px;
}

.register-subtitle {
  font-size: 14px;
  color: #94a3b8;
  margin: 0;
}

.register-form {
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
  justify-content: flex-start;
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

.register-footer {
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

.benefits-list {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.benefit-item {
  display: flex;
  gap: 16px;
  align-items: flex-start;
}

.benefit-icon {
  font-size: 32px;
  flex-shrink: 0;
}

.benefit-item h3 {
  font-size: 16px;
  font-weight: 600;
  color: #ffffff;
  margin: 0 0 4px 0;
}

.benefit-item p {
  font-size: 13px;
  color: #94a3b8;
  margin: 0;
}

.security-note {
  padding: 16px;
  background: rgba(34, 197, 94, 0.1);
  border: 1px solid rgba(34, 197, 94, 0.3);
  text-align: center;
}

.security-note p {
  font-size: 13px;
  color: #22c55e;
  margin: 0;
}

/* Responsive */
@media (max-width: 968px) {
  .register-container {
    grid-template-columns: 1fr;
  }

  .info-card {
    order: -1;
  }
}

@media (max-width: 480px) {
  .register-card,
  .info-card {
    padding: 24px;
  }

  .register-title {
    font-size: 24px;
  }
}
</style>
