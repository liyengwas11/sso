<script setup>
import { Head, usePage } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { ref, onMounted, onUnmounted } from "vue";
import QrScanner from "qr-scanner";

const props = defineProps({
  event: Object,
  today: Object, // { id, date, day_number } or null if event isn't running today
});

const page = usePage();
const staffName = page.props.auth.user?.name;

const videoEl = ref(null);
let scanner = null;
const cameraError = ref(null);

const result = ref(null); // { status, message, attendee }
const busy = ref(false);
const paused = ref(false);

async function handleDecode(decodedText) {
  if (busy.value || paused.value) return;
  busy.value = true;
  paused.value = true;

  await submitScan(decodedText, false);
  busy.value = false;
}

async function submitScan(token, override) {
  try {
    const res = await fetch(route("admin.events.scan.store", props.event.id), {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content ?? "",
      },
      body: JSON.stringify({ token, override }),
    });
    result.value = await res.json();
    result.value._token = token;
  } catch {
    result.value = { status: "error", message: "Network error — try scanning again.", attendee: null };
  }
}

function allowOverride() {
  if (!result.value?._token) return;
  submitScan(result.value._token, true);
}

function scanNext() {
  result.value = null;
  paused.value = false;
}

onMounted(async () => {
  try {
    scanner = new QrScanner(videoEl.value, (res) => handleDecode(res.data), {
      preferredCamera: "environment",
      highlightScanRegion: true,
      highlightCodeOutline: true,
    });
    await scanner.start();
  } catch (e) {
    cameraError.value = "Couldn't access the camera. Check permissions and reload.";
  }
});

onUnmounted(() => {
  scanner?.stop();
  scanner?.destroy();
});

const statusStyles = {
  granted: { bg: "bg-green-600", label: "ENTER" },
  override_granted: { bg: "bg-green-600", label: "ENTER (override)" },
  already_checked_in: { bg: "bg-amber-500", label: "ALREADY CHECKED IN" },
  not_accredited_today: { bg: "bg-red-600", label: "TURN AWAY — WRONG DAY" },
  not_event_day: { bg: "bg-slate-600", label: "EVENT NOT RUNNING TODAY" },
  revoked: { bg: "bg-red-600", label: "TURN AWAY — REVOKED" },
  wrong_event: { bg: "bg-red-600", label: "TURN AWAY — WRONG EVENT" },
  invalid: { bg: "bg-red-600", label: "TURN AWAY — INVALID" },
  error: { bg: "bg-slate-600", label: "ERROR" },
};
</script>

<template>
  <Head :title="`Scan — ${event.name}`" />

  <div class="min-h-screen bg-slate-950 text-white flex flex-col">
    <div class="px-4 py-3 flex items-center justify-between bg-slate-900">
      <div>
        <p class="text-sm font-medium">{{ event.name }}</p>
        <p class="text-xs text-slate-400">
          Scanning as {{ staffName }}<template v-if="today"> · Day {{ today.day_number }}</template>
        </p>
      </div>
    </div>

    <!-- Camera view (hidden once a result is showing, to save the scan for the next person) -->
    <div v-show="!result" class="relative flex-1">
      <video ref="videoEl" class="w-full h-full object-cover"></video>
      <div v-if="cameraError" class="absolute inset-0 flex items-center justify-center px-6 text-center text-sm text-red-300">
        {{ cameraError }}
      </div>
    </div>

    <!-- Result panel -->
    <div v-if="result" class="flex-1 flex flex-col">
      <div
        class="flex-1 flex flex-col items-center justify-center px-6 py-8 text-center"
        :class="statusStyles[result.status]?.bg ?? 'bg-slate-700'"
      >
        <img
          v-if="result.attendee?.photo_url"
          :src="result.attendee.photo_url"
          class="w-28 h-28 rounded-full object-cover border-4 border-white/30 mb-4"
        />
        <div v-else-if="result.attendee" class="w-28 h-28 rounded-full bg-white/20 flex items-center justify-center text-3xl mb-4">
          {{ result.attendee.name?.[0] }}
        </div>

        <p class="text-2xl font-bold mb-1">{{ statusStyles[result.status]?.label ?? result.status }}</p>

        <template v-if="result.attendee">
          <p class="text-lg font-medium">{{ result.attendee.name }}</p>
          <p class="text-sm opacity-80">{{ result.attendee.organisation }}</p>
          <p v-if="result.attendee.role_title" class="text-xs opacity-70 mt-1 uppercase tracking-wide">
            {{ result.attendee.role_title }}
          </p>
        </template>

        <p class="text-sm opacity-80 mt-4">{{ result.message }}</p>
      </div>

      <div class="p-4 bg-slate-900 space-y-2">
        <button
          v-if="result.status === 'already_checked_in'"
          @click="allowOverride"
          class="w-full bg-white text-slate-900 font-semibold rounded-md py-3"
        >
          Allow anyway (staff override)
        </button>
        <button @click="scanNext" class="w-full bg-slate-700 text-white font-semibold rounded-md py-3">
          Scan next
        </button>
      </div>
    </div>
  </div>
</template>
