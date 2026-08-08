<script setup>
import AdminLayout from "../../../Layouts/AdminLayout.vue";
import { Link, router } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { computed } from "vue";

const props = defineProps({
    event: Object,
    logs: Object,
    flaggedOnly: Boolean,
    summary: Object,
});

function toggleFlaggedOnly() {
    router.get(route("admin.events.reports.show", props.event.id),
        { flagged_only: !props.flaggedOnly ? 1 : undefined },
        { preserveState: true }
    );
}

const resultLabels = {
    granted: "Granted",
    override_granted: "Granted (override)",
    denied: "Denied",
    duplicate_attempt: "Duplicate Attempt",
};

const typeLabels = {
    'check-in': 'Check-In',
    'check-out': 'Check-Out',
};

// Helper to get attendee photo URL from the nested data structure
function getAttendeePhotoUrl(attendee) {
    if (!attendee) return null;

    // Check if attendee has media array with items
    if (attendee.media && Array.isArray(attendee.media) && attendee.media.length > 0) {
        const media = attendee.media[0];
        // Try original_url first, then fallback to url
        return media.original_url || media.url || null;
    }

    // Check for direct photo_url property (from accessor)
    if (attendee.photo_url) {
        return attendee.photo_url;
    }

    return null;
}

// Get initials for fallback avatar
function getInitials(name) {
    if (!name) return '?';
    return name
        .split(' ')
        .map(word => word[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

// Get the attendee from the log data
function getAttendee(log) {
    return log.event_pass?.attendee || null;
}

// Get the attendee name with fallback
function getAttendeeName(log) {
    const attendee = getAttendee(log);
    return attendee?.name || 'Unknown Attendee';
}

// Get the attendee organisation with fallback
function getAttendeeOrganisation(log) {
    const attendee = getAttendee(log);
    return attendee?.organisation || '';
}

// Get the attendee role with fallback
function getAttendeeRole(log) {
    const attendee = getAttendee(log);
    return attendee?.role_title || '';
}

// Get the attendee photo URL
function getAttendeePhoto(log) {
    const attendee = getAttendee(log);
    return getAttendeePhotoUrl(attendee);
}
</script>

<template>
    <AdminLayout>
        <Link :href="route('admin.events.index')" class="text-sm text-slate-400 hover:text-slate-700">← Events</Link>
        <h1 class="text-xl font-semibold text-slate-900 mb-6">{{ event.name }} — Reports</h1>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg border border-slate-200 p-4">
                <p class="text-xs text-slate-500 mb-1">Total passes</p>
                <p class="text-xl font-semibold text-slate-900">{{ summary.totalPasses }}</p>
            </div>
            <div class="bg-white rounded-lg border border-slate-200 p-4">
                <p class="text-xs text-slate-500 mb-1">Currently inside</p>
                <p class="text-xl font-semibold text-green-700">{{ summary.currentlyInside || 0 }}</p>
            </div>
            <div class="bg-white rounded-lg border border-slate-200 p-4">
                <p class="text-xs text-slate-500 mb-1">Checked out</p>
                <p class="text-xl font-semibold text-blue-700">{{ summary.checkedOut || 0 }}</p>
            </div>
            <div class="bg-white rounded-lg border border-slate-200 p-4"
                :class="summary.flaggedCount > 0 ? 'border-amber-300 bg-amber-50' : ''">
                <p class="text-xs text-slate-500 mb-1">Flagged</p>
                <p class="text-xl font-semibold"
                    :class="summary.flaggedCount > 0 ? 'text-amber-700' : 'text-slate-900'">
                    {{ summary.flaggedCount }}
                </p>
            </div>
        </div>

        <!-- Filter -->
        <div class="flex items-center justify-between mb-3">
            <button @click="toggleFlaggedOnly" class="text-sm px-3 py-1.5 rounded-md"
                :class="flaggedOnly ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'">
                {{ flaggedOnly ? "Showing flagged only" : "Show flagged only" }}
            </button>
        </div>

        <!-- Logs Table -->
        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Attendee</th>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 font-medium">Day</th>
                            <th class="px-4 py-3 font-medium">Result</th>
                            <th class="px-4 py-3 font-medium">Scanned by</th>
                            <th class="px-4 py-3 font-medium">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="log in logs.data" :key="log.id"
                            :class="log.flagged ? 'bg-amber-50' : ''">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <!-- Attendee Photo -->
                                    <div class="flex-shrink-0">
                                        <img v-if="getAttendeePhoto(log)"
                                             :src="getAttendeePhoto(log)"
                                             :alt="getAttendeeName(log)"
                                             class="w-10 h-10 rounded-full object-cover border border-slate-200" />
                                        <div v-else
                                             class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 text-sm font-medium border border-slate-200">
                                            {{ getInitials(getAttendeeName(log)) }}
                                        </div>
                                    </div>

                                    <!-- Attendee Info -->
                                    <div>
                                        <p class="font-medium text-slate-900">{{ getAttendeeName(log) }}</p>
                                        <p class="text-xs text-slate-500">{{ getAttendeeOrganisation(log) }}</p>
                                        <p v-if="getAttendeeRole(log)" class="text-xs text-slate-400">
                                            {{ getAttendeeRole(log) }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-0.5 rounded-full"
                                    :class="log.type === 'check-in' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'">
                                    {{ typeLabels[log.type] || log.type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                Day {{ log.event_day?.day_number ?? "—" }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-slate-600">
                                    {{ resultLabels[log.result] || log.result }}
                                </span>
                                <span v-if="log.flagged" class="ml-1 text-amber-500" title="Flagged for review">
                                    ⚠️
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ log.scanned_by?.name || 'Unknown' }}
                            </td>
                            <td class="px-4 py-3 text-slate-500">
                                {{ new Date(log.scanned_at).toLocaleString() }}
                            </td>
                        </tr>
                        <tr v-if="logs.data.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-inbox text-3xl mb-2 text-slate-300"></i>
                                    <p>No entries found.</p>
                                    <p class="text-xs mt-1">
                                        {{ flaggedOnly ? 'No flagged entries for this event.' : 'No scan logs recorded yet.' }}
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="logs.data && logs.data.length > 0" class="px-4 py-3 border-t border-slate-200 bg-slate-50">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-slate-500">
                        Showing {{ logs.from || 0 }} to {{ logs.to || 0 }} of {{ logs.total || 0 }} results
                    </div>
                    <div class="flex gap-1">
                        <Link v-if="logs.prev_page_url" :href="logs.prev_page_url"
                            class="px-3 py-1 rounded-md text-sm bg-white border border-slate-200 hover:bg-slate-50 transition-colors"
                            preserve-scroll>
                            Previous
                        </Link>
                        <span v-else class="px-3 py-1 rounded-md text-sm text-slate-400 bg-slate-100">
                            Previous
                        </span>

                        <Link v-if="logs.next_page_url" :href="logs.next_page_url"
                            class="px-3 py-1 rounded-md text-sm bg-white border border-slate-200 hover:bg-slate-50 transition-colors"
                            preserve-scroll>
                            Next
                        </Link>
                        <span v-else class="px-3 py-1 rounded-md text-sm text-slate-400 bg-slate-100">
                            Next
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
/* Optional: Add some hover effects */
tbody tr:hover {
    background-color: #f8fafc;
}

tbody tr.bg-amber-50:hover {
    background-color: #fef3c7;
}
</style>
