<script setup>
import AdminLayout from "../../../Layouts/AdminLayout.vue";
import { Link, router, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { ref, watch, nextTick, computed } from "vue";
import QRCode from "qrcode";

const props = defineProps({
    event: Object,
    passes: Object,
    filters: Object,
    today: Object,
});

const isMultiDay = computed(() => props.event.days?.length > 1);

// Event status badge color
const eventStatusColor = computed(() => {
    const status = props.event.status;
    return {
        'active': 'bg-green-100 text-green-700 border-green-200',
        'upcoming': 'bg-blue-100 text-blue-700 border-blue-200',
        'ended': 'bg-red-100 text-red-700 border-red-200',
    }[status] || 'bg-slate-100 text-slate-700 border-slate-200';
});

// Check-in status badge color
function getCheckInStatusColor(status) {
    return {
        'checked_in': 'bg-green-100 text-green-700 border-green-200',
        'checked_out': 'bg-blue-100 text-blue-700 border-blue-200',
        'not_checked_in': 'bg-slate-100 text-slate-400 border-slate-200',
    }[status] || 'bg-slate-100 text-slate-400 border-slate-200';
}

// Check-in status label
function getCheckInStatusLabel(status) {
    return {
        'checked_in': '✅ Checked In',
        'checked_out': '↩️ Checked Out',
        'not_checked_in': '⏳ Not Checked In',
    }[status] || status;
}

const search = ref(props.filters.search ?? "");
let searchTimeout = null;
watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route("admin.events.attendees.index", props.event.id), { search: value || undefined }, { preserveState: true, replace: true });
    }, 300);
});

const showAddModal = ref(false);
const editingAttendee = ref(null);
const photoPreview = ref(null);

const form = useForm({
    name: "",
    organisation: "",
    email: "",
    role_title: "",
    photo: null,
    days: [],
});

function openCreate() {
    editingAttendee.value = null;
    form.reset();
    photoPreview.value = null;
    form.days = [];
    showAddModal.value = true;
}

function openEdit(pass) {
    editingAttendee.value = pass;
    const attendee = pass.attendee;
    form.name = attendee.name;
    form.organisation = attendee.organisation ?? "";
    form.email = attendee.email ?? "";
    form.role_title = attendee.role_title ?? "";
    form.photo = null;
    form.days = pass.days.map(d => d.day_number);

    const photoUrl = getAttendeePhotoUrl(attendee);
    photoPreview.value = photoUrl;

    showAddModal.value = true;
}

function handleFileUpload(event) {
    const file = event.target.files[0];
    if (file) {
        form.photo = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            photoPreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}

function removePhoto() {
    photoPreview.value = null;
    form.photo = null;
    const fileInput = document.getElementById('photo-input');
    if (fileInput) {
        fileInput.value = '';
    }
}

function toggleDay(dayNumber) {
    const idx = form.days.indexOf(dayNumber);
    if (idx === -1) form.days.push(dayNumber);
    else form.days.splice(idx, 1);
}

function submitAdd() {
    const opts = {
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            photoPreview.value = null;
            editingAttendee.value = null;
            showAddModal.value = false;
        },
    };

    if (editingAttendee.value) {
        form.transform((data) => ({ ...data, _method: "patch" }))
            .post(route("admin.events.attendees.update", {
                event: props.event.id,
                attendee: editingAttendee.value.attendee.id
            }), opts);
    } else {
        form.post(route("admin.events.attendees.store", props.event.id), opts);
    }
}

const importForm = useForm({ file: null });
function submitImport() {
    importForm.post(route("admin.events.attendees.import", props.event.id), {
        forceFormData: true,
        onSuccess: () => importForm.reset(),
    });
}

function revoke(pass) {
    if (!confirm(`Revoke ${pass.attendee.name}'s pass? They won't be able to check in until reissued.`)) return;
    router.post(route("admin.passes.revoke", pass.id));
}

function dayLabel(pass) {
    if (!isMultiDay.value) return null;
    if (pass.days.length === props.event.days.length) return "All days";
    return "Day " + pass.days.map((d) => d.day_number).sort().join(", ");
}

const previewPass = ref(null);
const qrCanvas = ref(null);
async function openPreview(pass) {
    previewPass.value = pass;
    await nextTick();
    if (qrCanvas.value) {
        QRCode.toCanvas(qrCanvas.value, pass.token, { width: 200, margin: 1 });
    }
}

function getAttendeePhotoUrl(attendee) {
    if (!attendee) return null;
    if (attendee.media && Array.isArray(attendee.media) && attendee.media.length > 0) {
        return attendee.media[0].original_url || attendee.media[0].url || null;
    }
    if (attendee.photo_url) {
        return attendee.photo_url;
    }
    return null;
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

function getFullPhotoUrl(pass) {
    if (pass.attendee) {
        const url = getAttendeePhotoUrl(pass.attendee);
        if (url) return url;
        if (pass.attendee.photo_url) return pass.attendee.photo_url;
        if (pass.attendee.media && pass.attendee.media.length > 0) {
            const media = pass.attendee.media[0];
            return media.original_url || media.url || null;
        }
    }
    return null;
}
</script>

<template>
    <AdminLayout>
        <div class="flex items-center justify-between mb-1">
            <div>
                <Link :href="route('admin.events.index')" class="text-sm text-slate-400 hover:text-slate-700">← Events
                </Link>
                <h1 class="text-xl font-semibold text-slate-900">{{ event.name }} — Attendees</h1>
                <div class="flex items-center gap-3 mt-1">
                    <span class="text-xs px-3 py-1 rounded-full border font-medium" :class="eventStatusColor">
                        📅 {{ event.status_label || 'Unknown' }}
                    </span>
                    <span v-if="today" class="text-xs text-slate-500">
                        Today is Day {{ today.day_number }}
                    </span>
                </div>
            </div>
            <div class="flex gap-2">
                <a :href="route('admin.events.attendees.export', event.id)"
                    class="border border-slate-300 text-slate-700 text-sm font-medium rounded-md px-4 py-2 hover:bg-slate-50">
                    Export CSV
                </a>
                <button @click="openCreate"
                    class="bg-slate-900 text-white text-sm font-medium rounded-md px-4 py-2 hover:bg-slate-800">
                    Add attendee
                </button>
            </div>
        </div>

        <form @submit.prevent="submitImport" class="flex items-center gap-2 mb-6 mt-4">
            <input type="file" accept=".csv,.txt" @change="importForm.file = $event.target.files[0]" class="text-sm" />
            <button type="submit" :disabled="!importForm.file || importForm.processing"
                class="bg-slate-100 text-slate-700 text-sm font-medium rounded-md px-3 py-1.5 hover:bg-slate-200 disabled:opacity-50">
                Import CSV
            </button>
            <span class="text-xs text-slate-400">
                Columns: name, organisation, email, role_title — everyone imported is accredited for all days
            </span>
        </form>
        <p v-if="importForm.errors.file" class="text-sm text-red-600 mb-4">{{ importForm.errors.file }}</p>

        <input v-model="search" type="text" placeholder="Search attendees…"
            class="w-full max-w-md mb-4 rounded-md border border-slate-300 px-3 py-2 text-sm" />

        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Image</th>
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Organisation</th>
                            <th class="px-4 py-3 font-medium">Role</th>
                            <th v-if="isMultiDay" class="px-4 py-3 font-medium">Accredited</th>
                            <th class="px-4 py-3 font-medium">Pass Status</th>
                            <th class="px-4 py-3 font-medium">Check-in Status</th>
                            <th class="px-4 py-3 font-medium">Last check-in</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="pass in passes.data" :key="pass.id" class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-3">
                                <img v-if="getAttendeePhotoUrl(pass.attendee)" :src="getAttendeePhotoUrl(pass.attendee)"
                                    :alt="pass.attendee.name"
                                    class="h-12 w-12 rounded-full object-cover border border-slate-200" />
                                <div v-else
                                    class="h-12 w-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 text-sm font-medium border border-slate-200">
                                    {{ getInitials(pass.attendee.name) }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-900 font-medium">{{ pass.attendee.name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ pass.attendee.organisation }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ pass.attendee.role_title }}</td>
                            <td v-if="isMultiDay" class="px-4 py-3 text-slate-600">{{ dayLabel(pass) }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-0.5 rounded-full"
                                    :class="pass.status === 'active' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-600 border border-red-200'">
                                    {{ pass.status === 'active' ? '✅ Active' : '🚫 Revoked' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-0.5 rounded-full border font-medium"
                                    :class="getCheckInStatusColor(pass.current_status)">
                                    {{ getCheckInStatusLabel(pass.current_status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-500">
                                {{ pass.entry_logs?.[0]?.scanned_at ? new
                                    Date(pass.entry_logs[0].scanned_at).toLocaleString() : "—" }}
                            </td>
                            <td class="px-4 py-3 text-right space-x-3 whitespace-nowrap">
                                <button @click="openEdit(pass)"
                                    class="text-slate-500 hover:text-slate-900">Edit</button>
                                <button @click="openPreview(pass)" class="text-slate-500 hover:text-slate-900">View
                                    pass</button>
                                <button v-if="pass.status === 'active'" @click="revoke(pass)"
                                    class="text-red-500 hover:text-red-700">
                                    Revoke
                                </button>
                            </td>
                        </tr>
                        <tr v-if="passes.data.length === 0">
                            <td :colspan="isMultiDay ? 9 : 8" class="px-4 py-8 text-center text-slate-400">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-users text-3xl mb-2 text-slate-300"></i>
                                    <p>No attendees yet.</p>
                                    <p class="text-xs mt-1">Click "Add attendee" to get started.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add/Edit attendee modal -->
        <div v-if="showAddModal" class="fixed inset-0 bg-black/30 flex items-center justify-center px-4 z-50">
            <div class="bg-white rounded-lg w-full max-w-sm p-6 max-h-[90vh] overflow-y-auto shadow-xl">
                <h2 class="font-semibold text-slate-900 mb-4">
                    {{ editingAttendee ? "Edit attendee" : "Add attendee" }}
                </h2>
                <form @submit.prevent="submitAdd" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Photo</label>
                        <div v-if="photoPreview" class="mb-3">
                            <img :src="photoPreview" alt="Attendee preview"
                                class="w-16 h-16 rounded-full object-cover border-2 border-slate-200" />
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="flex-1">
                                <input id="photo-input" type="file" accept="image/*" @change="handleFileUpload"
                                    class="text-sm w-full file:mr-2 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer" />
                            </div>
                            <button v-if="photoPreview" type="button" @click="removePhoto"
                                class="text-sm text-red-500 hover:text-red-700">
                                Remove
                            </button>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Recommended: Square image, max 5MB</p>
                        <p v-if="form.errors.photo" class="mt-1 text-sm text-red-600">{{ form.errors.photo }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                        <input v-model="form.name" type="text" required
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900" />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Organisation</label>
                        <input v-model="form.organisation" type="text"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                        <p v-if="form.errors.organisation" class="mt-1 text-sm text-red-600">{{ form.errors.organisation
                            }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Role / title</label>
                        <input v-model="form.role_title" type="text"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                        <p v-if="form.errors.role_title" class="mt-1 text-sm text-red-600">{{ form.errors.role_title }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                        <input v-model="form.email" type="email"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                        <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                    </div>

                    <div v-if="isMultiDay">
                        <label class="block text-sm font-medium text-slate-700 mb-2">Accredited days</label>
                        <p class="text-xs text-slate-400 mb-2">Leave all unchecked to accredit for every day.</p>
                        <div class="space-y-1.5">
                            <label v-for="day in event.days" :key="day.id"
                                class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer hover:text-slate-900">
                                <input type="checkbox" :checked="form.days.includes(day.day_number)"
                                    @change="toggleDay(day.day_number)"
                                    class="rounded border-slate-300 text-slate-900 focus:ring-slate-900" />
                                Day {{ day.day_number }} — {{ new Date(day.date).toLocaleDateString([], {
                                    month: "short", day: "numeric"
                                }) }}
                            </label>
                        </div>
                        <p v-if="form.errors.days" class="mt-1 text-sm text-red-600">{{ form.errors.days }}</p>
                    </div>

                    <div class="flex gap-2 pt-2 border-t border-slate-200">
                        <button type="button" @click="showAddModal = false"
                            class="flex-1 border border-slate-300 text-slate-700 text-sm font-medium rounded-md py-2 hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit" :disabled="form.processing"
                            class="flex-1 bg-slate-900 text-white text-sm font-medium rounded-md py-2 hover:bg-slate-800 disabled:opacity-50">
                            {{ editingAttendee ? "Update" : "Save" }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Pass preview modal -->
        <div v-if="previewPass" class="fixed inset-0 bg-black/30 flex items-center justify-center px-4 z-50"
            @click.self="previewPass = null">
            <div class="bg-white rounded-lg w-full max-w-xs p-6 text-center shadow-xl">
                <img v-if="getFullPhotoUrl(previewPass)" :src="getFullPhotoUrl(previewPass)"
                    :alt="previewPass.attendee.name"
                    class="w-20 h-20 rounded-full object-cover mx-auto mb-3 border-2 border-slate-200" />
                <div v-else
                    class="w-20 h-20 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 text-xl mx-auto mb-3 border-2 border-slate-200">
                    {{ getInitials(previewPass.attendee.name) }}
                </div>

                <p class="font-semibold text-slate-900">{{ previewPass.attendee.name }}</p>
                <p class="text-sm text-slate-500">{{ previewPass.attendee.organisation }}</p>
                <p v-if="previewPass.attendee.role_title" class="text-xs text-slate-400 uppercase tracking-wide mb-1">
                    {{ previewPass.attendee.role_title }}
                </p>
                <p v-if="isMultiDay" class="text-xs text-slate-400 mb-3">{{ dayLabel(previewPass) }}</p>
                <canvas ref="qrCanvas" class="mx-auto mt-3"></canvas>
                <button @click="previewPass = null"
                    class="mt-4 text-sm text-slate-500 hover:text-slate-900">Close</button>
            </div>
        </div>
    </AdminLayout>
</template>
