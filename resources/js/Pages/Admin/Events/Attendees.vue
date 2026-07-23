<script setup>
import AdminLayout from "../../../Layouts/AdminLayout.vue";
import { Link, router, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { ref, watch, nextTick, computed } from "vue";
import QRCode from "qrcode";

const props = defineProps({
  event: Object, // includes .days: [{id, date, day_number}, ...]
  passes: Object,
  filters: Object,
});

const isMultiDay = computed(() => props.event.days.length > 1);

const search = ref(props.filters.search ?? "");
let searchTimeout = null;
watch(search, (value) => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    router.get(route("admin.events.attendees.index", props.event.id), { search: value || undefined }, { preserveState: true, replace: true });
  }, 300);
});

const showAddModal = ref(false);
const form = useForm({
  name: "",
  organisation: "",
  email: "",
  role_title: "",
  photo: null,
  days: [], // day_numbers; empty = all days
});

function toggleDay(dayNumber) {
  const idx = form.days.indexOf(dayNumber);
  if (idx === -1) form.days.push(dayNumber);
  else form.days.splice(idx, 1);
}

function submitAdd() {
  form.post(route("admin.events.attendees.store", props.event.id), {
    forceFormData: true,
    onSuccess: () => {
      form.reset();
      showAddModal.value = false;
    },
  });
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
  QRCode.toCanvas(qrCanvas.value, pass.token, { width: 200, margin: 1 });
}
</script>

<template>
  <AdminLayout>
    <div class="flex items-center justify-between mb-1">
      <div>
        <Link :href="route('admin.events.index')" class="text-sm text-slate-400 hover:text-slate-700">← Events</Link>
        <h1 class="text-xl font-semibold text-slate-900">{{ event.name }} — Attendees</h1>
      </div>
      <div class="flex gap-2">
        <a :href="route('admin.events.attendees.export', event.id)"
          class="border border-slate-300 text-slate-700 text-sm font-medium rounded-md px-4 py-2 hover:bg-slate-50">
          Export CSV
        </a>
        <button @click="showAddModal = true"
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
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-left">
          <tr>
            <th class="px-4 py-3 font-medium">Name</th>
            <th class="px-4 py-3 font-medium">Organisation</th>
            <th class="px-4 py-3 font-medium">Role</th>
            <th v-if="isMultiDay" class="px-4 py-3 font-medium">Accredited</th>
            <th class="px-4 py-3 font-medium">Status</th>
            <th class="px-4 py-3 font-medium">Last check-in</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="pass in passes.data" :key="pass.id">
            <td class="px-4 py-3 text-slate-900">{{ pass.attendee.name }}</td>
            <td class="px-4 py-3 text-slate-600">{{ pass.attendee.organisation }}</td>
            <td class="px-4 py-3 text-slate-600">{{ pass.attendee.role_title }}</td>
            <td v-if="isMultiDay" class="px-4 py-3 text-slate-600">{{ dayLabel(pass) }}</td>
            <td class="px-4 py-3">
              <span class="text-xs px-2 py-0.5 rounded-full"
                :class="pass.status === 'active' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'">
                {{ pass.status }}
              </span>
            </td>
            <td class="px-4 py-3 text-slate-500">
              {{ pass.entryLogs?.[0] ? new Date(pass.entryLogs[0].scanned_at).toLocaleString() : "—" }}
            </td>
            <td class="px-4 py-3 text-right space-x-3">
              <button @click="openPreview(pass)" class="text-slate-500 hover:text-slate-900">View pass</button>
              <button v-if="pass.status === 'active'" @click="revoke(pass)" class="text-red-500 hover:text-red-700">
                Revoke
              </button>
            </td>
          </tr>
          <tr v-if="passes.data.length === 0">
            <td :colspan="isMultiDay ? 7 : 6" class="px-4 py-8 text-center text-slate-400">No attendees yet.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add attendee modal -->
    <div v-if="showAddModal" class="fixed inset-0 bg-black/30 flex items-center justify-center px-4 z-50">
      <div class="bg-white rounded-lg w-full max-w-sm p-6 max-h-[90vh] overflow-y-auto">
        <h2 class="font-semibold text-slate-900 mb-4">Add attendee</h2>
        <form @submit.prevent="submitAdd" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
            <input v-model="form.name" type="text" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Organisation</label>
            <input v-model="form.organisation" type="text" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Role / title</label>
            <input v-model="form.role_title" type="text" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
            <input v-model="form.email" type="email" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Photo</label>
            <input type="file" accept="image/*" @change="form.photo = $event.target.files[0]" class="text-sm" />
            <p v-if="form.errors.photo" class="mt-1 text-sm text-red-600">{{ form.errors.photo }}</p>
          </div>

          <div v-if="isMultiDay">
            <label class="block text-sm font-medium text-slate-700 mb-2">Accredited days</label>
            <p class="text-xs text-slate-400 mb-2">Leave all unchecked to accredit for every day.</p>
            <div class="space-y-1.5">
              <label v-for="day in event.days" :key="day.id" class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" :checked="form.days.includes(day.day_number)" @change="toggleDay(day.day_number)"
                  class="rounded border-slate-300" />
                Day {{ day.day_number }} — {{ new Date(day.date).toLocaleDateString([], { month: "short", day: "numeric" }) }}
              </label>
            </div>
          </div>

          <div class="flex gap-2 pt-2">
            <button type="button" @click="showAddModal = false"
              class="flex-1 border border-slate-300 text-slate-700 text-sm font-medium rounded-md py-2">Cancel</button>
            <button type="submit" :disabled="form.processing"
              class="flex-1 bg-slate-900 text-white text-sm font-medium rounded-md py-2 disabled:opacity-50">Save</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Pass preview modal -->
    <div v-if="previewPass" class="fixed inset-0 bg-black/30 flex items-center justify-center px-4 z-50" @click.self="previewPass = null">
      <div class="bg-white rounded-lg w-full max-w-xs p-6 text-center">
        <img v-if="previewPass.attendee.photo_url" :src="previewPass.attendee.photo_url"
          class="w-20 h-20 rounded-full object-cover mx-auto mb-3" />
        <p class="font-semibold text-slate-900">{{ previewPass.attendee.name }}</p>
        <p class="text-sm text-slate-500">{{ previewPass.attendee.organisation }}</p>
        <p v-if="previewPass.attendee.role_title" class="text-xs text-slate-400 uppercase tracking-wide mb-1">
          {{ previewPass.attendee.role_title }}
        </p>
        <p v-if="isMultiDay" class="text-xs text-slate-400 mb-3">{{ dayLabel(previewPass) }}</p>
        <canvas ref="qrCanvas" class="mx-auto mt-3"></canvas>
        <button @click="previewPass = null" class="mt-4 text-sm text-slate-500 hover:text-slate-900">Close</button>
      </div>
    </div>
  </AdminLayout>
</template>
