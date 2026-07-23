<script setup>
import AdminLayout from "../../../Layouts/AdminLayout.vue";
import { Link, router } from "@inertiajs/vue3";
import { route } from "ziggy-js";

const props = defineProps({
  flags: Object,
  statusFilter: String,
});

function markReviewed(flag) {
  router.post(route("admin.flags.mark-reviewed", flag.id), {}, { preserveScroll: true });
}

function setFilter(status) {
  router.get(route("admin.flags.index"), { status }, { preserveState: true });
}

const reasonLabels = {
  already_checked_in: "Duplicate scan — pass already used",
  staff_override: "Staff override — allowed a repeat entry",
};
</script>

<template>
  <AdminLayout>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-semibold text-slate-900">Flagged Scans</h1>
      <div class="flex gap-1 text-sm">
        <button @click="setFilter('unreviewed')"
          class="px-3 py-1.5 rounded-md"
          :class="statusFilter === 'unreviewed' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'">
          Unreviewed
        </button>
        <button @click="setFilter('all')"
          class="px-3 py-1.5 rounded-md"
          :class="statusFilter === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'">
          All
        </button>
      </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-left">
          <tr>
            <th class="px-4 py-3 font-medium">Attendee</th>
            <th class="px-4 py-3 font-medium">Event</th>
            <th class="px-4 py-3 font-medium">Reason</th>
            <th class="px-4 py-3 font-medium">Scanned by</th>
            <th class="px-4 py-3 font-medium">When</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="flag in flags.data" :key="flag.id">
            <td class="px-4 py-3 text-slate-900">
              {{ flag.eventPass.attendee.name }}
              <span class="text-slate-400">· {{ flag.eventPass.attendee.organisation }}</span>
            </td>
            <td class="px-4 py-3 text-slate-600">{{ flag.eventPass.event.name }}</td>
            <td class="px-4 py-3 text-slate-600">{{ reasonLabels[flag.reason] ?? flag.reason }}</td>
            <td class="px-4 py-3 text-slate-600">{{ flag.scannedBy?.name }}</td>
            <td class="px-4 py-3 text-slate-500">{{ new Date(flag.scanned_at).toLocaleString() }}</td>
            <td class="px-4 py-3 text-right">
              <button v-if="!flag.reviewed_at" @click="markReviewed(flag)" class="text-slate-500 hover:text-slate-900">
                Mark reviewed
              </button>
              <span v-else class="text-xs text-slate-400">Reviewed</span>
            </td>
          </tr>
          <tr v-if="flags.data.length === 0">
            <td colspan="6" class="px-4 py-8 text-center text-slate-400">Nothing flagged.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
