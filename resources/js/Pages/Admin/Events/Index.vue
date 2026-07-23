<script setup>
import AdminLayout from "../../../Layouts/AdminLayout.vue";
import { Link, router, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { ref } from "vue";

const props = defineProps({
  events: Object,
});

const showModal = ref(false);
const editingEvent = ref(null);

const form = useForm({
  name: "",
  description: "",
  venue: "",
  start_date: "",
  end_date: "",
  is_recurring: false,
  cover: null,
});

function openCreate() {
  editingEvent.value = null;
  form.reset();
  showModal.value = true;
}

function openEdit(event) {
  editingEvent.value = event;
  form.name = event.name;
  form.description = event.description ?? "";
  form.venue = event.venue ?? "";
  form.start_date = event.start_date?.slice(0, 10) ?? "";
  form.end_date = event.end_date?.slice(0, 10) ?? "";
  form.is_recurring = event.is_recurring;
  form.cover = null;
  showModal.value = true;
}

function submit() {
  const opts = { forceFormData: true, onSuccess: () => (showModal.value = false) };
  if (editingEvent.value) {
    // Laravel doesn't parse multipart PATCH bodies — Inertia's form
    // helper adds the _method spoof automatically when you .post()
    // with transform, but simplest here is routing edits through POST
    // with a method override field so the file upload survives.
    form.transform((data) => ({ ...data, _method: "patch" })).post(route("admin.events.update", editingEvent.value.id), opts);
  } else {
    form.post(route("admin.events.store"), opts);
  }
}

function cloneNextYear(event) {
  if (!confirm(`Create next year's "${event.name}" and issue fresh passes to all current attendees?`)) return;
  router.post(route("admin.events.clone-next-year", event.id));
}

function destroyEvent(event) {
  if (!confirm(`Delete "${event.name}"? This removes all its passes and entry logs.`)) return;
  router.delete(route("admin.events.destroy", event.id));
}

function formatDate(iso) {
  return new Date(iso).toLocaleDateString([], { year: "numeric", month: "short", day: "numeric" });
}

function dayCount(event) {
  return event.days?.length ?? 0;
}
</script>

<template>
  <AdminLayout>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-semibold text-slate-900">Events</h1>
      <button
        @click="openCreate"
        class="bg-slate-900 text-white text-sm font-medium rounded-md px-4 py-2 hover:bg-slate-800 transition"
      >
        Create event
      </button>
    </div>

    <div class="grid gap-4">
      <div
        v-for="event in events.data"
        :key="event.id"
        class="bg-white rounded-lg border border-slate-200 overflow-hidden flex items-stretch"
      >
        <img v-if="event.cover_url" :src="event.cover_url" class="w-32 object-cover" />
        <div class="flex-1 p-5 flex items-start justify-between">
          <div>
            <div class="flex items-center gap-2">
              <p class="font-medium text-slate-900">{{ event.name }}</p>
              <span v-if="event.is_recurring" class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full">
                Recurring
              </span>
              <span v-if="dayCount(event) > 1" class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full">
                {{ dayCount(event) }}-day event
              </span>
            </div>
            <p class="text-sm text-slate-500 mt-0.5">
              {{ formatDate(event.start_date) }}
              <template v-if="dayCount(event) > 1"> – {{ formatDate(event.end_date) }}</template>
              · {{ event.venue || "No venue set" }} · {{ event.passes_count }} passes issued
            </p>
          </div>

          <div class="flex gap-3 shrink-0 ml-4 items-center">
            <Link :href="route('admin.events.attendees.index', event.id)" class="text-sm text-slate-500 hover:text-slate-900">
              Attendees
            </Link>
            <Link :href="route('admin.events.scan.show', event.id)" class="text-sm text-slate-500 hover:text-slate-900">
              Scan
            </Link>
            <Link :href="route('admin.events.reports.show', event.id)" class="text-sm text-slate-500 hover:text-slate-900">
              Reports
            </Link>
            <button v-if="event.is_recurring" @click="cloneNextYear(event)" class="text-sm text-blue-600 hover:text-blue-800">
              Clone for next year
            </button>
            <button @click="openEdit(event)" class="text-sm text-slate-500 hover:text-slate-900">Edit</button>
            <button @click="destroyEvent(event)" class="text-sm text-red-500 hover:text-red-700">Delete</button>
          </div>
        </div>
      </div>

      <p v-if="events.data.length === 0" class="text-sm text-slate-400 text-center py-8">No events yet.</p>
    </div>

    <!-- Create/Edit modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/30 flex items-center justify-center px-4 z-50">
      <div class="bg-white rounded-lg w-full max-w-md p-6 max-h-[90vh] overflow-y-auto">
        <h2 class="font-semibold text-slate-900 mb-4">
          {{ editingEvent ? "Edit event" : "Create event" }}
        </h2>

        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
            <input v-model="form.name" type="text" required
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900" />
            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Cover image</label>
            <input type="file" accept="image/*" @change="form.cover = $event.target.files[0]" class="text-sm" />
            <p v-if="form.errors.cover" class="mt-1 text-sm text-red-600">{{ form.errors.cover }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Venue</label>
            <input v-model="form.venue" type="text"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Start date</label>
              <input v-model="form.start_date" type="date" required
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
              <p v-if="form.errors.start_date" class="mt-1 text-sm text-red-600">{{ form.errors.start_date }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">End date</label>
              <input v-model="form.end_date" type="date" required
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
              <p v-if="form.errors.end_date" class="mt-1 text-sm text-red-600">{{ form.errors.end_date }}</p>
            </div>
          </div>
          <p class="text-xs text-slate-400 -mt-2">
            If the end date is after the start date, each calendar day in between becomes a selectable day when accrediting attendees.
          </p>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
            <textarea v-model="form.description" rows="2"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
          </div>

          <label class="flex items-center justify-between">
            <span class="text-sm font-medium text-slate-700">Recurring annually</span>
            <button
              type="button"
              @click="form.is_recurring = !form.is_recurring"
              class="w-11 h-6 rounded-full transition relative"
              :class="form.is_recurring ? 'bg-slate-900' : 'bg-slate-200'"
            >
              <span
                class="absolute top-0.5 w-5 h-5 rounded-full bg-white transition-all"
                :class="form.is_recurring ? 'left-5' : 'left-0.5'"
              ></span>
            </button>
          </label>

          <div class="flex gap-2 pt-2">
            <button type="button" @click="showModal = false"
              class="flex-1 border border-slate-300 text-slate-700 text-sm font-medium rounded-md py-2 hover:bg-slate-50">
              Cancel
            </button>
            <button type="submit" :disabled="form.processing"
              class="flex-1 bg-slate-900 text-white text-sm font-medium rounded-md py-2 hover:bg-slate-800 disabled:opacity-50">
              Save
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>
