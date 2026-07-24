<script setup>
import AdminLayout from "../../Layouts/AdminLayout.vue";
import { Link } from "@inertiajs/vue3";
import { route } from "ziggy-js";

const props = defineProps({
  stats: Object,
  events: Array,
});

function formatDate(iso) {
  return new Date(iso).toLocaleDateString([], { year: "numeric", month: "short", day: "numeric" });
}

function getEventStatus(startDate, endDate) {
  const now = new Date();
  now.setHours(0, 0, 0, 0);
  const start = new Date(startDate);
  start.setHours(0, 0, 0, 0);
  const end = new Date(endDate);
  end.setHours(0, 0, 0, 0);

  if (now > end) {
    return { label: 'Ended', color: 'bg-red-100 text-red-700 border-red-200' };
  } else if (now >= start && now <= end) {
    return { label: 'Active', color: 'bg-green-100 text-green-700 border-green-200' };
  } else {
    return { label: 'Upcoming', color: 'bg-blue-100 text-blue-700 border-blue-200' };
  }
}

function getInitials(name) {
  if (!name) return '?';
  return name
    .split(' ')
    .map(word => word[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);
}
</script>

<template>
  <AdminLayout>
    <h1 class="text-xl font-semibold text-slate-900 mb-6">Dashboard</h1>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
      <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-sm">
        <p class="text-sm text-slate-500 mb-1">Upcoming events</p>
        <p class="text-2xl font-semibold text-slate-900">{{ stats.upcomingEvents }}</p>
      </div>
      <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-sm">
        <p class="text-sm text-slate-500 mb-1">Checked in today</p>
        <p class="text-2xl font-semibold text-slate-900">{{ stats.checkedInToday }}</p>
      </div>
      <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-sm" :class="stats.unreviewedFlags > 0 ? 'border-amber-300 bg-amber-50' : ''">
        <p class="text-sm text-slate-500 mb-1">Unreviewed flags</p>
        <p class="text-2xl font-semibold" :class="stats.unreviewedFlags > 0 ? 'text-amber-700' : 'text-slate-900'">
          {{ stats.unreviewedFlags }}
        </p>
      </div>
    </div>

    <!-- Events List -->
    <h2 class="text-sm font-medium text-slate-500 mb-3">Upcoming / current events</h2>
    <div class="grid gap-3">
      <Link
        v-for="event in events"
        :key="event.id"
        :href="route('admin.events.attendees.index', event.id)"
        class="bg-white rounded-lg border border-slate-200 overflow-hidden hover:border-slate-400 transition-all hover:shadow-md group"
      >
        <div class="flex items-stretch">
          <!-- Event Image -->
          <div class="w-32 flex-shrink-0 bg-slate-100 relative overflow-hidden">
            <img
              v-if="event.cover_url"
              :src="event.cover_url"
              :alt="event.name"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            />
            <div v-else class="w-full h-full flex items-center justify-center text-slate-300 bg-slate-50">
              <i class="fas fa-calendar-alt text-3xl"></i>
            </div>
          </div>

          <!-- Event Info -->
          <div class="flex-1 p-4 flex items-center justify-between">
            <div>
              <div class="flex items-center gap-2 flex-wrap">
                <p class="font-medium text-slate-900">{{ event.name }}</p>
                <span class="text-xs px-2 py-0.5 rounded-full border font-medium" :class="getEventStatus(event.start_date, event.end_date).color">
                  {{ getEventStatus(event.start_date, event.end_date).label }}
                </span>
              </div>
              <p class="text-sm text-slate-500 mt-0.5">
                <i class="far fa-calendar-alt mr-1"></i>
                {{ formatDate(event.start_date) }}
                <template v-if="event.start_date !== event.end_date"> – {{ formatDate(event.end_date) }}</template>
              </p>
              <p class="text-sm text-slate-500">
                <i class="fas fa-map-pin mr-1"></i>
                {{ event.venue || "No venue set" }}
              </p>
              <p class="text-xs text-slate-400 mt-1 flex items-center gap-3">
                <span><i class="fas fa-user-check mr-1"></i>{{ event.passes_count || 0 }} passes</span>
                <span v-if="event.days?.length > 1">
                  <i class="fas fa-calendar-day mr-1"></i>{{ event.days.length }}-day event
                </span>
              </p>
            </div>

            <div class="flex items-center gap-2 ml-4 text-slate-400 group-hover:text-slate-700 transition-colors">
              <span class="text-sm">View</span>
              <i class="fas fa-chevron-right text-xs"></i>
            </div>
          </div>
        </div>
      </Link>

      <div v-if="events.length === 0" class="bg-white rounded-lg border border-slate-200 p-8 text-center">
        <i class="fas fa-calendar-plus text-4xl text-slate-300 mb-3 block"></i>
        <p class="text-sm text-slate-400">No upcoming events.</p>
        <Link
          :href="route('admin.events.index')"
          class="text-sm text-slate-900 font-medium hover:underline mt-2 inline-block"
        >
          Create an event →
        </Link>
      </div>
    </div>
  </AdminLayout>
</template>
