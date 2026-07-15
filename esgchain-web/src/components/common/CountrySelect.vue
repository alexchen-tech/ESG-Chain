<template>
  <div class="country-select-wrap" ref="wrap">
    <input
      ref="inputEl"
      class="form-input country-input"
      :value="displayValue"
      :placeholder="placeholder || '搜尋國家...'"
      autocomplete="off"
      @input="onInput"
      @focus="onFocus"
      @blur="onBlur"
      @keydown.esc="onEsc"
      @keydown.enter.prevent="onEnter"
      @keydown.arrow-down.prevent="onArrowDown"
      @keydown.arrow-up.prevent="onArrowUp"
    />
    <div v-if="open && filtered.length > 0" class="country-dropdown">
      <div
        v-for="(c, i) in filtered" :key="c.code"
        class="country-option"
        :class="{ 'option-active': i === activeIndex }"
        @mousedown.prevent="selectCountry(c.code)"
      >
        <span class="option-name">{{ c.name }}</span>
        <span class="option-code font-mono">{{ c.code }}</span>
      </div>
      <div v-if="hasMore" class="country-hint">繼續輸入以縮小範圍</div>
    </div>
    <div v-else-if="open && query && filtered.length === 0" class="country-dropdown">
      <div class="country-empty">無符合國家</div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { COUNTRIES, findCountry, countryDisplay } from '@/utils/countries'

const MAX_RESULTS = 10

export default defineComponent({
  name: 'CountrySelect',

  props: {
    modelValue: { type: String as () => string | null, default: null },
    placeholder: { type: String, default: '搜尋國家...' },
  },

  emits: ['update:modelValue'],

  data() {
    return {
      query: '',
      displayValue: '',
      open: false,
      activeIndex: 0,
      blurTimer: null as ReturnType<typeof setTimeout> | null,
    }
  },

  computed: {
    filtered() {
      const q = this.query.toLowerCase().trim()
      if (!q) return COUNTRIES.slice(0, MAX_RESULTS)
      return COUNTRIES.filter(c =>
        c.code.toLowerCase().includes(q) ||
        c.name.includes(q) ||
        c.name_en.toLowerCase().includes(q)
      ).slice(0, MAX_RESULTS)
    },
    hasMore(): boolean {
      const q = this.query.toLowerCase().trim()
      if (!q) return COUNTRIES.length > MAX_RESULTS
      return COUNTRIES.filter(c =>
        c.code.toLowerCase().includes(q) ||
        c.name.includes(q) ||
        c.name_en.toLowerCase().includes(q)
      ).length > MAX_RESULTS
    },
  },

  watch: {
    modelValue(val: string | null) {
      this.syncDisplay(val)
    },
  },

  mounted() {
    this.syncDisplay(this.modelValue)
  },

  methods: {
    syncDisplay(code: string | null | undefined) {
      this.displayValue = countryDisplay(code ?? null)
      this.query = ''
    },

    onFocus() {
      this.activeIndex = 0
      this.open = true
    },

    onInput(e: Event) {
      const val = (e.target as HTMLInputElement).value
      this.query = val
      this.displayValue = val
      this.open = true
      this.activeIndex = 0
    },

    onBlur() {
      this.blurTimer = setTimeout(() => {
        this.open = false
        const q = this.query.trim()
        if (!q) {
          this.$emit('update:modelValue', null)
          this.displayValue = ''
          this.query = ''
        } else {
          this.syncDisplay(this.modelValue)
        }
      }, 120)
    },

    onEsc() {
      this.open = false
      this.syncDisplay(this.modelValue)
    },

    onEnter() {
      if (this.open && this.filtered.length > 0) {
        this.selectCountry(this.filtered[this.activeIndex]?.code)
      }
    },

    onArrowDown() {
      this.activeIndex = Math.min(this.activeIndex + 1, this.filtered.length - 1)
    },

    onArrowUp() {
      this.activeIndex = Math.max(this.activeIndex - 1, 0)
    },

    selectCountry(code: string) {
      if (this.blurTimer) clearTimeout(this.blurTimer)
      const c = findCountry(code)
      this.displayValue = c ? `${c.name} (${c.code})` : code
      this.query = ''
      this.open = false
      this.activeIndex = 0
      this.$emit('update:modelValue', code.toUpperCase())
    },
  },
})
</script>

<style scoped>
.country-select-wrap {
  position: relative;
  width: 100%;
}

.country-input {
  width: 100%;
}

.country-dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 6px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  z-index: 50;
  overflow: hidden;
  max-height: 280px;
  overflow-y: auto;
}

.country-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  cursor: pointer;
  font-size: 14px;
  transition: background 0.1s;
}
.country-option:hover,
.option-active {
  background: var(--surface-2);
}

.option-name { color: var(--text-primary); }
.option-code { font-size: 12px; color: var(--text-secondary); }

.country-hint {
  padding: 6px 12px;
  font-size: 12px;
  color: var(--text-secondary);
  border-top: 1px solid var(--border);
  background: var(--surface-2);
}

.country-empty {
  padding: 10px 12px;
  font-size: 13px;
  color: var(--text-secondary);
  text-align: center;
}
</style>
