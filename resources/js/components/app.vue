<template>
  <div v-if="loading" class="global-loader">
    <DotLottieVue 
      v-if="lottieUrl && !lottieError"
      style="height: 500px; width: 500px" 
      autoplay 
      loop 
      :src="lottieUrl" 
      @error="handleLottieError"
    />
    <div v-else class="loader-content">
      <div class="loader-spinner"></div>
      <p class="loader-text">Loading...</p>
    </div>
  </div>
  <template v-else>
    <Navbar />
    <router-view />
  </template>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import Navbar from './navbar.vue'
import { DotLottieVue } from '@lottiefiles/dotlottie-vue'

const loading = ref(true)
const lottieError = ref(false)

// LottieFiles animation URL
// Using direct URL format from LottieFiles
const lottieUrl = ref('https://lottie.host/00224a0e-52e3-4fdb-bae1-93ffad4b12eb/WMfByuyj0h.lottie')

const handleLottieError = (error) => {
  console.error('Lottie animation error:', error)
  lottieError.value = true
  // Falls back to CSS spinner automatically
}

onMounted(() => {
  setTimeout(() => {
    loading.value = false
  }, 2500)
})
</script>

<style>
html, body {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  background: #000000;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  color: #e5e7eb;
}

.global-loader {
  position: fixed;
  inset: 0;
  background: linear-gradient(135deg, #0a0e27 0%, #000000 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.loader-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
}

.loader-spinner {
  width: 50px;
  height: 50px;
  border: 3px solid #1a1f3a;
  border-top-color: #22c55e;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.loader-text {
  color: #94a3b8;
  font-size: 14px;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 1px;
}
</style>
