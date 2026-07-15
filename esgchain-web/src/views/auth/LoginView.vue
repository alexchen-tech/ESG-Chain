<template>
  <div class="login-page">
    <div class="login-card">
      <div class="login-header">
        <h1 class="login-title">ESG·Chain</h1>
        <p class="login-subtitle">永續供應鏈管理平台</p>
      </div>

      <!-- 測試帳號快速登入 -->
      <div class="demo-accounts">
        <div class="demo-label">測試帳號快速登入</div>
        <div class="demo-list">
          <button
            v-for="account in demoAccounts"
            :key="account.email"
            class="demo-btn"
            :disabled="authStore.isLoading"
            @click="quickLogin(account)"
          >
            <span class="demo-role">{{ account.role }}</span>
            <span class="demo-email">{{ account.email }}</span>
          </button>
        </div>
      </div>

      <div class="divider"><span>或手動輸入</span></div>

      <form class="login-form" @submit.prevent="handleLogin">
        <div class="form-group">
          <label for="email">Email</label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            placeholder="your@email.com"
            required
            :disabled="authStore.isLoading"
          />
        </div>

        <div class="form-group">
          <label for="password">密碼</label>
          <input
            id="password"
            v-model="form.password"
            type="password"
            placeholder="••••••••"
            required
            :disabled="authStore.isLoading"
          />
        </div>

        <p v-if="errorMsg" class="error-msg">{{ errorMsg }}</p>

        <button type="submit" class="btn-login" :disabled="authStore.isLoading">
          <span v-if="authStore.isLoading">登入中...</span>
          <span v-else>登入</span>
        </button>
      </form>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

export default defineComponent({
  name: 'LoginView',

  setup() {
    return { authStore: useAuthStore(), router: useRouter() }
  },

  data() {
    return {
      form: { email: '', password: '' },
      errorMsg: '',
      demoAccounts: [
        { role: '管理員', email: 'admin@esgchain.com', password: 'demo1234' },
        { role: '採購商', email: 'buyer@esgchain.com', password: 'demo1234' },
        { role: '永續長', email: 'sustain@esgchain.com', password: 'demo1234' },
        { role: '分析師', email: 'analyst@esgchain.com', password: 'demo1234' },
        { role: '供應商', email: 'supplier1@tpsteel.com.tw', password: 'demo1234' },
      ],
    }
  },

  methods: {
    async quickLogin(account: { email: string; password: string }) {
      this.form.email = account.email
      this.form.password = account.password
      await this.handleLogin()
    },
    async handleLogin() {
      this.errorMsg = ''
      try {
        const redirectPath = await this.authStore.login(this.form.email, this.form.password)
        this.router.push(redirectPath)
      } catch {
        this.errorMsg = '帳號或密碼錯誤，請重新輸入'
      }
    },
  },
})
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: var(--bg);
}

.login-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 48px 40px;
  width: 100%;
  max-width: 420px;
}

.login-title {
  font-family: var(--font-title);
  font-size: 28px;
  color: var(--accent);
  margin-bottom: 4px;
}

.login-subtitle {
  color: var(--text-secondary);
  font-size: 14px;
  margin-bottom: 32px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  margin-bottom: 6px;
  color: var(--text-primary);
}

.form-group input {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: var(--surface);
  color: var(--text-primary);
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s;
}

.form-group input:focus {
  border-color: var(--accent);
}

.error-msg {
  color: #dc2626;
  font-size: 13px;
  margin-bottom: 16px;
}

.btn-login {
  width: 100%;
  padding: 12px;
  background: var(--accent);
  color: #fff;
  border: none;
  border-radius: 6px;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-login:hover:not(:disabled) {
  background: var(--accent-hover);
}

.btn-login:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* 測試帳號 */
.demo-accounts {
  margin-bottom: 20px;
}

.demo-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-secondary);
  margin-bottom: 8px;
}

.demo-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.demo-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 6px;
  cursor: pointer;
  text-align: left;
  transition: all 0.15s;
  width: 100%;
}

.demo-btn:hover:not(:disabled) {
  background: #f0ede6;
  border-color: var(--accent);
}

.demo-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.demo-role {
  font-size: 12px;
  font-weight: 600;
  color: var(--accent);
  min-width: 52px;
  background: rgba(26,77,62,0.08);
  padding: 2px 8px;
  border-radius: 99px;
  text-align: center;
}

.demo-email {
  font-size: 13px;
  color: var(--text-secondary);
  font-family: var(--font-mono);
}

/* 分隔線 */
.divider {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 20px 0;
  color: var(--text-secondary);
  font-size: 12px;
}

.divider::before,
.divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--border);
}
</style>
