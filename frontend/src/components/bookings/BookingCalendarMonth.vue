<script setup lang="ts">
interface CalendarCell {
  key: string
  date: string | null
  day: number | null
  count: number
}

interface Props {
  monthLabel: string
  cells: CalendarCell[]
}

defineProps<Props>()

const emit = defineEmits<{
  (event: 'previous'): void
  (event: 'next'): void
  (event: 'select-date', date: string): void
}>()

function selectDate(date: string | null): void {
  if (!date) {
    return
  }

  emit('select-date', date)
}
</script>

<template>
  <article class="panel calendar-panel">
    <header class="calendar-head">
      <button type="button" class="nav-btn" @click="emit('previous')">Previous</button>
      <h2>{{ monthLabel }}</h2>
      <button type="button" class="nav-btn" @click="emit('next')">Next</button>
    </header>

    <div class="weekday-row">
      <span>Mon</span>
      <span>Tue</span>
      <span>Wed</span>
      <span>Thu</span>
      <span>Fri</span>
      <span>Sat</span>
      <span>Sun</span>
    </div>

    <div class="calendar-grid">
      <button
        v-for="cell in cells"
        :key="cell.key"
        type="button"
        class="calendar-cell"
        :class="{ empty: !cell.date, hasBooking: cell.count > 0 }"
        :disabled="!cell.date"
        @click="selectDate(cell.date)"
      >
        <span v-if="cell.day !== null" class="day">{{ cell.day }}</span>
        <span v-if="cell.count > 0" class="dot"></span>
      </button>
    </div>

    <p class="calendar-help">Click a day with a dot to filter list bookings for that date.</p>
  </article>
</template>

<style scoped>
.panel {
  background: #fff;
  border: 1px solid rgba(229, 176, 95, 0.4);
  border-radius: 12px;
  box-shadow: 0 8px 20px rgba(15, 51, 65, 0.07);
  padding: 0.9rem;
}

.calendar-head {
  align-items: center;
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.7rem;
}

h2 {
  color: #0f3341;
  font-size: 1.02rem;
}

.nav-btn {
  background: #fff;
  border: 1px solid #d8dee3;
  border-radius: 7px;
  color: #0f3341;
  cursor: pointer;
  font-size: 0.8rem;
  font-weight: 700;
  padding: 0.34rem 0.56rem;
}

.weekday-row {
  color: #5c6f7a;
  display: grid;
  font-size: 0.74rem;
  font-weight: 700;
  grid-template-columns: repeat(7, 1fr);
  margin-bottom: 0.35rem;
  text-align: center;
  text-transform: uppercase;
}

.calendar-grid {
  display: grid;
  gap: 0.28rem;
  grid-template-columns: repeat(7, 1fr);
}

.calendar-cell {
  align-items: center;
  background: #fff;
  border: 1px solid #e7edf1;
  border-radius: 8px;
  cursor: pointer;
  display: grid;
  min-height: 2.6rem;
  place-items: center;
  position: relative;
}

.calendar-cell.empty {
  background: #fafcfd;
  cursor: default;
}

.calendar-cell.hasBooking {
  border-color: #c57632;
}

.day {
  color: #0f3341;
  font-size: 0.82rem;
  font-weight: 700;
}

.dot {
  background: #c57632;
  border-radius: 999px;
  bottom: 0.34rem;
  height: 0.35rem;
  position: absolute;
  width: 0.35rem;
}

.calendar-help {
  color: #5d707c;
  font-size: 0.82rem;
  margin-top: 0.6rem;
}
</style>
