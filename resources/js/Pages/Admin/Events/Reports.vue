<script setup>
import AdminLayout from "../../../Layouts/AdminLayout.vue";
import { Link, router } from "@inertiajs/vue3";
import { route } from "ziggy-js";

const props = defineProps({
  event: Object,
  logs: Object,
  flaggedOnly: Boolean,
  summary: Object,
});

function toggleFlaggedOnly() {
  router.get(route("admin.events.reports.show", props.event.id), { flagged_only: !props.flaggedOnly ? 1 : undefined }, { preserveState: true });
}

const resultLabels = {
  granted: "Granted",
  override_granted: "Granted (override)",
  denied: "Denied",
};
</script>

<template>
  <AdminLayout>
    <Link :href="route('admin.events.index')" class="text-sm text-slate-400 hover:text-slate-700">← Events</Link>
    <h1 class="text-xl font-semibold text-slate-900 mb-6">{{ event.name }} — Reports</h1>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">Total passes</p>
        <p class="text-xl font-semibold text-slate-900">{{ summary.totalPasses }}</p>
      </div>
      <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">Checked in</p>
        <p class="text-xl font-semibold text-green-700">{{ summary.checkedIn }}</p>
      </div>
      <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">Not checked in</p>
        <p class="text-xl font-semibold text-slate-500">{{ summary.notCheckedIn }}</p>
      </div>
      <div class="bg-white rounded-lg border border-slate-200 p-4" :class="summary.flaggedCount > 0 ? 'border-amber-300 bg-amber-50' : ''">
        <p class="text-xs text-slate-500 mb-1">Flagged</p>
        <p class="text-xl font-semibold" :class="summary.flaggedCount > 0 ? 'text-amber-700' : 'text-slate-900'">
          {{ summary.flaggedCount }}
        </p>
      </div>
    </div>

    <div class="flex items-center justify-between mb-3">
      <button @click="toggleFlaggedOnly" class="text-sm px-3 py-1.5 rounded-md"
        :class="flaggedOnly ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'">
        {{ flaggedOnly ? "Showing flagged only" : "Show flagged only" }}
      </button>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-left">
          <tr>
            <th class="px-4 py-3 font-medium">Attendee</th>
            <th class="px-4 py-3 font-medium">Day</th>
            <th class="px-4 py-3 font-medium">Result</th>
            <th class="px-4 py-3 font-medium">Scanned by</th>
            <th class="px-4 py-3 font-medium">When</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="log in logs.data" :key="log.id" :class="log.flagged ? 'bg-amber-50' : ''">
            <td class="px-4 py-3 text-slate-900">{{ log.eventPass.attendee.name }}</td>
            <td class="px-4 py-3 text-slate-600">Day {{ log.eventDay?.day_number ?? "—" }}</td>
            <td class="px-4 py-3 text-slate-600">{{ resultLabels[log.result] ?? log.result }}</td>
            <td class="px-4 py-3 text-slate-600">{{ log.scannedBy?.name }}</td>
            <td class="px-4 py-3 text-slate-500">{{ new Date(log.scanned_at).toLocaleString() }}</td>
          </tr>
          <tr v-if="logs.data.length === 0">
            <td colspan="5" class="px-4 py-8 text-center text-slate-400">No entries yet.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
