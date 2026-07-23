<script setup>
import AdminLayout from "../../Layouts/AdminLayout.vue";
import { Link } from "@inertiajs/vue3";
import { route } from "ziggy-js";

defineProps({
  stats: Object,
  events: Array,
});

function formatDate(iso) {
  return new Date(iso).toLocaleDateString([], { year: "numeric", month: "short", day: "numeric" });
}
</script>

<template>
  <AdminLayout>
    <h1 class="text-xl font-semibold text-slate-900 mb-6">Dashboard</h1>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
      <div class="bg-white rounded-lg border border-slate-200 p-5">
        <p class="text-sm text-slate-500 mb-1">Upcoming events</p>
        <p class="text-2xl font-semibold text-slate-900">{{ stats.upcomingEvents }}</p>
      </div>
      <div class="bg-white rounded-lg border border-slate-200 p-5">
        <p class="text-sm text-slate-500 mb-1">Checked in today</p>
        <p class="text-2xl font-semibold text-slate-900">{{ stats.checkedInToday }}</p>
      </div>
      <div class="bg-white rounded-lg border border-slate-200 p-5" :class="stats.unreviewedFlags > 0 ? 'border-amber-300 bg-amber-50' : ''">
        <p class="text-sm text-slate-500 mb-1">Unreviewed flags</p>
        <p class="text-2xl font-semibold" :class="stats.unreviewedFlags > 0 ? 'text-amber-700' : 'text-slate-900'">
          {{ stats.unreviewedFlags }}
        </p>
      </div>
    </div>

    <h2 class="text-sm font-medium text-slate-500 mb-3">Upcoming / current events</h2>
    <div class="grid gap-3">
      <Link
        v-for="event in events"
        :key="event.id"
        :href="route('admin.events.attendees.index', event.id)"
        class="bg-white rounded-lg border border-slate-200 p-4 flex items-center justify-between hover:border-slate-400 transition"
      >
        <div>
          <p class="font-medium text-slate-900">{{ event.name }}</p>
          <p class="text-sm text-slate-500">{{ formatDate(event.start_date) }} – {{ formatDate(event.end_date) }}</p>
        </div>
        <span class="text-sm text-slate-400">{{ event.passes_count }} passes</span>
      </Link>
      <p v-if="events.length === 0" class="text-sm text-slate-400">No upcoming events.</p>
    </div>
  </AdminLayout>
</template>
