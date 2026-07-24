<script setup>
import AdminLayout from "../../../Layouts/AdminLayout.vue";
import { Link, router } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { ref, computed } from "vue";

const props = defineProps({
    flags: Object,
    statusFilter: String,
});

const searchQuery = ref('');

function getAttendeePhoto(attendee) {
    if (!attendee || !attendee.media) return null;

    if (Array.isArray(attendee.media)) {
        const profilePhoto = attendee.media.find(m => m.collection_name === 'profile_photos');
        if (profilePhoto) {
            return profilePhoto.original_url || profilePhoto.url || null;
        }
        const firstMedia = attendee.media[0];
        return firstMedia?.original_url || firstMedia?.url || null;
    }

    return attendee.media.original_url || attendee.media.url || null;
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

function handleSearch() {
    router.get(route("admin.flags.index"), {
        status: props.statusFilter,
        search: searchQuery.value
    }, {
        preserveState: true,
        preserveScroll: true
    });
}

function markReviewed(flag) {
    router.post(route("admin.flags.mark-reviewed", flag.id), {}, {
        preserveScroll: true,
    });
}

function setFilter(status) {
    router.get(route("admin.flags.index"), { status }, {
        preserveState: true,
        preserveScroll: true
    });
}

const reasonLabels = {
    already_checked_in: "Duplicate scan — pass already used",
    staff_override: "Staff override — allowed a repeat entry",
    wrong_event: "Wrong event — attendee not registered for this event",
    not_accredited_today: "Not accredited for today's date",
    revoked: "Access revoked",
    invalid: "Invalid QR code",
    not_checked_in: "Attempted check-out without check-in",
    already_checked_out: "Duplicate check-out attempt",
};

const typeLabels = {
    'check-in': 'Check-In',
    'check-out': 'Check-Out',
};

const hasFlags = computed(() => {
    return props.flags?.data?.length > 0;
});

function formatDate(dateString) {
    if (!dateString) return "-";
    return new Date(dateString).toLocaleString();
}
</script>

<template>
    <AdminLayout>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-xl font-semibold text-slate-900">Flagged Scans</h1>
            <div class="flex gap-1 text-sm">
                <button @click="setFilter('unreviewed')" class="px-3 py-1.5 rounded-md transition-colors"
                    :class="statusFilter === 'unreviewed' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                    Unreviewed ({{ flags?.unreviewed_count || 0 }})
                </button>
                <button @click="setFilter('all')" class="px-3 py-1.5 rounded-md transition-colors"
                    :class="statusFilter === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                    All ({{ flags?.total || 0 }})
                </button>
            </div>
        </div>
        <div class="mb-4">
            <div class="flex gap-2">
                <input type="text" v-model="searchQuery" @keyup.enter="handleSearch"
                    placeholder="Search by attendee name, email, or organisation..."
                    class="flex-1 px-3 py-2 border border-slate-200 rounded-md text-sm" />
                <button @click="handleSearch" class="px-4 py-2 bg-slate-900 text-white rounded-md text-sm">
                    Search
                </button>
            </div>
        </div>
        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">

                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Attendee</th>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 font-medium">Event</th>
                            <th class="px-4 py-3 font-medium">Reason</th>
                            <th class="px-4 py-3 font-medium">Scanned by</th>
                            <th class="px-4 py-3 font-medium">When</th>
                            <th class="px-4 py-3 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="flag in flags.data" :key="flag.id" class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0">
                                        <img v-if="getAttendeePhoto(flag.event_pass?.attendee)"
                                            :src="getAttendeePhoto(flag.event_pass?.attendee)"
                                            :alt="flag.event_pass?.attendee?.name"
                                            class="w-10 h-10 rounded-full object-cover" />
                                        <div v-else
                                            class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-medium">
                                            {{ getInitials(flag.event_pass?.attendee?.name) }}
                                        </div>
                                    </div>

                                    <div>
                                        <div class="font-medium text-slate-900">{{ flag.event_pass?.attendee?.name }}
                                        </div>
                                        <div class="text-xs text-slate-400">{{ flag.event_pass?.attendee?.organisation
                                            }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-0.5 rounded-full"
                                    :class="flag.type === 'check-in' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'">
                                    {{ typeLabels[flag.type] || flag.type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ flag.eventPass?.event?.name || 'Unknown Event' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="{
                                        'bg-yellow-100 text-yellow-800': flag.reason === 'already_checked_in',
                                        'bg-blue-100 text-blue-800': flag.reason === 'staff_override',
                                        'bg-red-100 text-red-800': ['wrong_event', 'not_accredited_today', 'revoked', 'invalid', 'not_checked_in'].includes(flag.reason),
                                        'bg-gray-100 text-gray-800': !['already_checked_in', 'staff_override', 'wrong_event', 'not_accredited_today', 'revoked', 'invalid', 'not_checked_in'].includes(flag.reason)
                                    }">
                                    {{ reasonLabels[flag.reason] ?? flag.reason }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ flag.scannedBy?.name || 'Unknown' }}
                            </td>
                            <td class="px-4 py-3 text-slate-500">
                                {{ formatDate(flag.scanned_at) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button v-if="!flag.reviewed_at" @click="markReviewed(flag)"
                                    class="text-sm font-medium text-indigo-600 hover:text-indigo-900 transition-colors"
                                    :disabled="flag.is_processing">
                                    <span v-if="flag.is_processing" class="inline-block animate-spin mr-1">⟳</span>
                                    Mark reviewed
                                </button>
                                <span v-else class="text-xs text-green-600 font-medium">
                                    <span class="inline-block mr-1">✓</span> Reviewed
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!hasFlags">
                            <td colspan="7" class="px-4 py-8 text-center text-slate-400">
                                <div class="flex flex-col items-center">
                                    <span class="text-4xl mb-2">🔍</span>
                                    <p class="text-sm">No flagged scans found.</p>
                                    <p class="text-xs text-slate-400 mt-1">
                                        {{ statusFilter === 'unreviewed' ? 'All flags have been reviewed.' :
                                            'No flags have been recorded yet.' }}
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="hasFlags" class="px-4 py-3 border-t border-slate-200 bg-slate-50">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-slate-500">
                        Showing {{ flags.from || 0 }} to {{ flags.to || 0 }} of {{ flags.total || 0 }} results
                    </div>
                    <div class="flex gap-1">
                        <Link v-if="flags.prev_page_url" :href="flags.prev_page_url"
                            class="px-3 py-1 rounded-md text-sm bg-white border border-slate-200 hover:bg-slate-50 transition-colors"
                            preserve-scroll>
                            Previous
                        </Link>
                        <span v-else class="px-3 py-1 rounded-md text-sm text-slate-400 bg-slate-100">
                            Previous
                        </span>

                        <Link v-if="flags.next_page_url" :href="flags.next_page_url"
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
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.animate-spin {
    animation: spin 1s linear infinite;
}

.transition-colors {
    transition: background-color 0.15s ease-in-out, color 0.15s ease-in-out;
}
</style>
