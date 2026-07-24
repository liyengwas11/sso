<script setup>
import { Head, usePage, Link } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { ref, onMounted, onUnmounted, nextTick, computed, watch } from "vue";
import { QrcodeStream } from "vue-qrcode-reader";
import { Html5Qrcode } from "html5-qrcode";

// ========== PROPS ==========
const props = defineProps({
    event: Object,
    today: Object,
    type: {
        type: String,
        default: 'check-in',
        validator: (value) => ['check-in', 'check-out'].includes(value)
    },
    gates: Array,
    title: String,
    scannerConfig: Object,
});

// ========== STATE ==========
const page = usePage();
const staffName = page.props.auth.user?.name;

// Scanner states
const showQrScanner = ref(false);
const showBarcodeScanner = ref(false);
const camera = ref("auto");
const error = ref("");
const lastDetectedCode = ref("");
const barcodeScanner = ref(null);
const scannerContainer = ref(null);
const isLoading = ref(false);
const loadingMessage = ref("");
const loadingType = ref("");
const selectedGate = ref('');

// Dropdown states
const showGateDropdown = ref(false);
const gateSearch = ref('');
const gateDropdownRef = ref(null);

// Result states
const result = ref(null);
const busy = ref(false);
const paused = ref(false);

// Toast states
const showSuccessToast = ref(false);
const successMessage = ref("");
const toastTimeout = ref(null);
const toastType = ref('success');

// Camera error state
const cameraError = ref(null);

// ========== COMPUTED ==========
const isCheckIn = computed(() => props.type === 'check-in');
const isCheckOut = computed(() => props.type === 'check-out');

// Filtered gates based on search
const filteredGates = computed(() => {
    if (!props.gates) return [];
    if (!gateSearch.value) return props.gates;
    const search = gateSearch.value.toLowerCase();
    return props.gates.filter(gate =>
        gate.toLowerCase().includes(search)
    );
});

// Display value for selected gate
const selectedGateDisplay = computed(() => {
    if (!selectedGate.value) return 'Select gate/location...';
    return selectedGate.value;
});

const statusStyles = computed(() => ({
    granted: {
        bg: isCheckIn.value ? 'bg-emerald-500' : 'bg-blue-500',
        label: isCheckIn.value ? '✅ CHECK-IN SUCCESSFUL' : '✅ CHECK-OUT SUCCESSFUL',
        icon: isCheckIn.value ? 'fa-door-open' : 'fa-door-closed'
    },
    override_granted: {
        bg: isCheckIn.value ? 'bg-emerald-500' : 'bg-blue-500',
        label: isCheckIn.value ? '✅ CHECK-IN (OVERRIDE)' : '✅ CHECK-OUT (OVERRIDE)',
        icon: 'fa-check-double'
    },
    already_checked_in: {
        bg: 'bg-amber-500',
        label: '⚠️ ALREADY CHECKED IN',
        icon: 'fa-exclamation-triangle'
    },
    already_checked_out: {
        bg: 'bg-amber-500',
        label: '⚠️ ALREADY CHECKED OUT',
        icon: 'fa-exclamation-triangle'
    },
    not_checked_in: {
        bg: 'bg-rose-500',
        label: '❌ CHECK-OUT DENIED',
        icon: 'fa-times-circle'
    },
    not_accredited_today: {
        bg: 'bg-rose-500',
        label: '❌ TURN AWAY — WRONG DAY',
        icon: 'fa-calendar-times'
    },
    not_event_day: {
        bg: 'bg-slate-500',
        label: '📅 EVENT NOT RUNNING TODAY',
        icon: 'fa-calendar-day'
    },
    revoked: {
        bg: 'bg-rose-500',
        label: '❌ TURN AWAY — REVOKED',
        icon: 'fa-ban'
    },
    wrong_event: {
        bg: 'bg-rose-500',
        label: '❌ TURN AWAY — WRONG EVENT',
        icon: 'fa-exclamation-circle'
    },
    invalid: {
        bg: 'bg-rose-500',
        label: '❌ TURN AWAY — INVALID',
        icon: 'fa-question-circle'
    },
    error: {
        bg: 'bg-slate-500',
        label: '⚠️ ERROR',
        icon: 'fa-exclamation-circle'
    },
}));

// ========== DROPDOWN METHODS ==========
function toggleGateDropdown() {
    showGateDropdown.value = !showGateDropdown.value;
    if (showGateDropdown.value) {
        gateSearch.value = '';
        nextTick(() => {
            const searchInput = document.getElementById('gate-search-input');
            if (searchInput) searchInput.focus();
        });
    }
}

function selectGate(gate) {
    selectedGate.value = gate;
    showGateDropdown.value = false;
    gateSearch.value = '';
}

function clearGate() {
    selectedGate.value = '';
    showGateDropdown.value = false;
    gateSearch.value = '';
}

// Close dropdown when clicking outside
function handleClickOutside(event) {
    if (gateDropdownRef.value && !gateDropdownRef.value.contains(event.target)) {
        showGateDropdown.value = false;
    }
}

// ========== SCANNER METHODS ==========
const onDetect = (detectedCodes) => {
    if (detectedCodes.length > 0) {
        const code = detectedCodes[0].rawValue;
        if (code !== lastDetectedCode.value) {
            lastDetectedCode.value = code;
            handleDecode(code);
            toggleQrScanner();
        }
    }
};

const paintBoundingBox = (detectedCodes, ctx) => {
    for (const detectedCode of detectedCodes) {
        const { boundingBox } = detectedCode;
        const { x, y, width, height } = boundingBox;
        ctx.lineWidth = 3;
        ctx.strokeStyle = "#10b981";
        ctx.shadowColor = "#10b981";
        ctx.shadowBlur = 10;
        ctx.strokeRect(x, y, width, height);
    }
};

const onInit = async (promise) => {
    try {
        await promise;
        cameraError.value = null;
    } catch (err) {
        console.error("Scanner initialization error:", err);
        if (err.name === "NotAllowedError") {
            cameraError.value = "Camera permission denied. Please allow camera access in your browser settings and reload.";
        } else if (err.name === "NotFoundError") {
            cameraError.value = "No camera found. Please connect a camera and reload.";
        } else if (err.name === "NotSupportedError") {
            cameraError.value = "Camera is not supported on this device.";
        } else if (err.name === "NotReadableError") {
            cameraError.value = "Camera is already in use by another application.";
        } else if (err.name === "OverconstrainedError") {
            cameraError.value = "Camera constraints not satisfied. Try using a different camera.";
        } else {
            cameraError.value = `Couldn't access the camera: ${err.message || 'Unknown error'}. Check permissions and reload.`;
        }
    }
};

const toggleQrScanner = () => {
    showQrScanner.value = !showQrScanner.value;
    if (!showQrScanner.value) {
        camera.value = "off";
        if (barcodeScanner.value) {
            barcodeScanner.value.stop();
            barcodeScanner.value = null;
        }
    } else {
        camera.value = "auto";
        if (showBarcodeScanner.value) {
            toggleBarcodeScanner();
        }
    }
};

// Barcode Scanner Methods
const initBarcodeScanner = () => {
    try {
        if (barcodeScanner.value) {
            barcodeScanner.value.stop();
            barcodeScanner.value = null;
        }

        const scannerElement = document.getElementById("barcode-scanner");
        if (!scannerElement) {
            throw new Error("Scanner element not found");
        }

        barcodeScanner.value = new Html5Qrcode("barcode-scanner");

        const config = {
            fps: 10,
            qrbox: { width: 250, height: 250 },
            rememberLastUsedCamera: true,
            showTorchButtonIfSupported: true,
            showZoomSliderIfSupported: true,
            defaultZoomValueIfSupported: 2,
            aspectRatio: 1.0,
            formatsToSupport: [Html5Qrcode.QRCode],
        };

        barcodeScanner.value.start(
            { facingMode: "environment" },
            config,
            (decodedText) => {
                if (decodedText !== lastDetectedCode.value) {
                    lastDetectedCode.value = decodedText;
                    handleDecode(decodedText);
                    toggleBarcodeScanner();
                }
            },
            (errorMessage) => {
                if (errorMessage !== "NotFoundException") {
                    console.error("Barcode scanning error:", errorMessage);
                }
            }
        );

        cameraError.value = null;
    } catch (err) {
        console.error("Error initializing barcode scanner:", err);
        cameraError.value = "Error initializing barcode scanner: " + err.message;
    }
};

const toggleBarcodeScanner = () => {
    showBarcodeScanner.value = !showBarcodeScanner.value;
    if (showBarcodeScanner.value) {
        cameraError.value = null;
        if (showQrScanner.value) {
            toggleQrScanner();
        }
        nextTick(() => {
            initBarcodeScanner();
        });
    } else {
        if (barcodeScanner.value) {
            barcodeScanner.value.stop();
            barcodeScanner.value = null;
        }
    }
};

// ========== SCAN LOGIC ==========
async function handleDecode(decodedText) {
    if (busy.value || paused.value || !decodedText) return;

    isLoading.value = true;
    loadingType.value = "scan";
    loadingMessage.value = "Processing QR code...";

    try {
        await submitScan(decodedText, false);
    } catch (error) {
        console.error("Scan error:", error);
        showToast("Error processing scan. Please try again.", 'error');
    } finally {
        isLoading.value = false;
        loadingType.value = "";
        loadingMessage.value = "";
    }
}

async function submitScan(token, override) {
    try {
        const url = route("admin.events.scan.process", {
            event: props.event.id,
            type: props.type
        });

        const payload = {
            token,
            override,
            gate_location: selectedGate.value || null
        };

        const res = await fetch(url, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content ?? "",
            },
            body: JSON.stringify(payload),
        });

        const data = await res.json();
        result.value = data;
        result.value._token = token;

        // Show appropriate toast
        if (data.status === 'granted' || data.status === 'override_granted') {
            const action = isCheckIn.value ? 'checked in' : 'checked out';
            showToast(`Successfully ${action}: ${data.attendee?.name || 'Attendee'}`, 'success');
        } else if (data.status === 'already_checked_in') {
            showToast('Duplicate check-in detected!', 'warning');
        } else if (data.status === 'not_checked_in') {
            showToast('Cannot check out: not checked in', 'error');
        } else if (data.status === 'already_checked_out') {
            showToast('Already checked out', 'warning');
        }

        paused.value = true;

    } catch (error) {
        console.error("Submit error:", error);
        result.value = {
            status: "error",
            message: "Network error — try scanning again.",
            attendee: null
        };
        showToast("Network error. Please try again.", 'error');
    }
}

function allowOverride() {
    if (!result.value?._token) return;
    isLoading.value = true;
    loadingMessage.value = "Processing override...";
    submitScan(result.value._token, true).finally(() => {
        isLoading.value = false;
        loadingMessage.value = "";
    });
}

function scanNext() {
    result.value = null;
    paused.value = false;
    lastDetectedCode.value = "";

    if (showBarcodeScanner.value && barcodeScanner.value) {
        try {
            barcodeScanner.value.resume();
        } catch (e) {
            console.error("Error resuming scanner:", e);
        }
    }
}

function retryCamera() {
    cameraError.value = null;
    if (showQrScanner.value) {
        toggleQrScanner();
        setTimeout(() => {
            toggleQrScanner();
        }, 500);
    } else if (showBarcodeScanner.value) {
        toggleBarcodeScanner();
        setTimeout(() => {
            toggleBarcodeScanner();
        }, 500);
    }
}

// ========== TOAST ==========
const showToast = (message, type = 'success') => {
    successMessage.value = message;
    toastType.value = type;
    showSuccessToast.value = true;
    if (toastTimeout.value) clearTimeout(toastTimeout.value);
    toastTimeout.value = setTimeout(() => {
        showSuccessToast.value = false;
    }, 5000);
};

// ========== WATCHERS ==========
watch(() => props.type, (newType) => {
    result.value = null;
    paused.value = false;
    lastDetectedCode.value = "";
    showToast(`Switched to ${newType === 'check-in' ? 'Check-In' : 'Check-Out'} mode`, 'success');
});

// ========== LIFECYCLE ==========
onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    camera.value = "off";
    if (barcodeScanner.value) {
        barcodeScanner.value.stop();
        barcodeScanner.value = null;
    }
    if (toastTimeout.value) {
        clearTimeout(toastTimeout.value);
    }
    document.removeEventListener('click', handleClickOutside);
});

function getInitials(name) {
    if (!name) return '?';
    return name
        .split(' ')
        .map(word => word[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

// Go back to previous page
function goBack() {
    window.history.back();
}
</script>

<template>
    <Head :title="`${title} — ${event.name}`" />

    <!-- White page background -->
    <div class="min-h-screen bg-white">

        <!-- Dark container with margins -->
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8 max-w-7xl">

            <!-- Dark themed card/container -->
            <div class="bg-gradient-to-b from-slate-900 to-slate-800 rounded-2xl shadow-2xl overflow-hidden">

                <!-- Header -->
                <header class="px-4 sm:px-6 py-4 bg-slate-900/50 backdrop-blur-sm border-b border-slate-700/50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <Link :href="route('admin.events.index')"
                                  class="text-slate-400 hover:text-white transition-colors">
                                <i class="fas fa-arrow-left"></i>
                            </Link>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h1 class="text-sm sm:text-base font-semibold text-white truncate max-w-[150px] sm:max-w-xs">
                                        {{ event.name }}
                                    </h1>
                                    <span class="text-[10px] px-2 py-0.5 rounded-full font-medium uppercase tracking-wider"
                                          :class="isCheckIn ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-blue-500/20 text-blue-300 border border-blue-500/30'">
                                        {{ isCheckIn ? 'Check-In' : 'Check-Out' }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    <i class="fas fa-user-circle mr-1"></i>
                                    {{ staffName }}
                                    <template v-if="today">
                                        · <i class="far fa-calendar-alt mr-1"></i>Day {{ today.day_number }}
                                    </template>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <!-- Back Button -->
                            <button @click="goBack"
                                    class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 rounded-lg text-xs font-medium transition-colors border border-slate-700 text-white flex items-center gap-1.5">
                                <i class="fas fa-times"></i>
                                Back
                            </button>

                            <!-- Switch Button -->
                            <Link :href="route('admin.events.scan', { event: event.id, type: isCheckIn ? 'check-out' : 'check-in' })"
                                  class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 rounded-lg text-xs font-medium transition-colors border border-slate-700 text-white flex items-center gap-1.5">
                                <i class="fas fa-exchange-alt"></i>
                                Switch
                            </Link>
                        </div>
                    </div>
                </header>

                <!-- Gate Selection - Searchable Dropdown -->
                <div v-if="gates && gates.length > 0" class="px-4 sm:px-6 py-3 bg-slate-800/50 border-b border-slate-700/30">
                    <div class="relative" ref="gateDropdownRef">
                        <label class="block text-xs text-slate-400 mb-1.5 font-medium">
                            <i class="fas fa-map-pin mr-1.5"></i>
                            Gate / Location
                        </label>

                        <!-- Dropdown Toggle Button -->
                        <div
                            @click="toggleGateDropdown"
                            class="flex items-center justify-between bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 cursor-pointer hover:border-slate-500 transition-colors"
                            :class="{'border-emerald-500/50 ring-2 ring-emerald-500/20': showGateDropdown}"
                        >
                            <span class="text-sm truncate" :class="selectedGate ? 'text-white' : 'text-slate-400'">
                                {{ selectedGateDisplay }}
                            </span>
                            <div class="flex items-center gap-2">
                                <button
                                    v-if="selectedGate"
                                    @click.stop="clearGate"
                                    class="text-slate-400 hover:text-white transition-colors"
                                >
                                    <i class="fas fa-times-circle text-sm"></i>
                                </button>
                                <i class="fas fa-chevron-down text-slate-400 text-xs transition-transform"
                                   :class="{'rotate-180': showGateDropdown}"></i>
                            </div>
                        </div>

                        <!-- Dropdown Menu -->
                        <div v-if="showGateDropdown"
                             class="absolute z-50 w-full mt-1.5 bg-slate-800 border border-slate-700 rounded-lg shadow-xl overflow-hidden animate-slide-down">

                            <!-- Search Input -->
                            <div class="p-2 border-b border-slate-700">
                                <div class="relative">
                                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                    <input
                                        id="gate-search-input"
                                        v-model="gateSearch"
                                        type="text"
                                        placeholder="Search gates..."
                                        class="w-full bg-slate-700 text-white text-sm rounded-lg pl-8 pr-3 py-1.5 border border-slate-600 focus:outline-none focus:border-emerald-500/50 focus:ring-2 focus:ring-emerald-500/20 transition-all"
                                        @keydown.esc="showGateDropdown = false"
                                        @keydown.enter="filteredGates.length === 1 && selectGate(filteredGates[0])"
                                    />
                                </div>
                            </div>

                            <!-- Options List -->
                            <div class="max-h-60 overflow-y-auto">
                                <div v-if="filteredGates.length === 0"
                                     class="px-3 py-4 text-sm text-slate-400 text-center">
                                    <i class="fas fa-search mr-1.5"></i>
                                    No gates found
                                </div>

                                <div
                                    v-for="gate in filteredGates"
                                    :key="gate"
                                    @click="selectGate(gate)"
                                    class="px-3 py-2.5 hover:bg-slate-700 cursor-pointer transition-colors flex items-center justify-between group"
                                    :class="{'bg-slate-700/50': selectedGate === gate}"
                                >
                                    <span class="text-sm text-white">{{ gate }}</span>
                                    <div v-if="selectedGate === gate" class="text-emerald-400">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div v-else class="text-slate-600 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <i class="fas fa-chevron-right text-xs"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="p-2 border-t border-slate-700 flex justify-end">
                                <button
                                    @click="showGateDropdown = false"
                                    class="text-xs text-slate-400 hover:text-white transition-colors px-3 py-1"
                                >
                                    <i class="fas fa-times mr-1"></i>
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Area -->
                <div class="flex flex-col items-center justify-center min-h-[50vh] p-4 sm:p-6">

                    <!-- Camera view (idle state) -->
                    <div v-show="!result && !showQrScanner && !showBarcodeScanner"
                         class="w-full max-w-md flex flex-col items-center justify-center py-8 sm:py-12">

                        <div class="w-24 h-24 sm:w-32 sm:h-32 rounded-full bg-slate-800/50 border-2 border-dashed border-slate-600 flex items-center justify-center mb-4 sm:mb-6">
                            <i class="fas fa-camera text-4xl sm:text-5xl text-slate-500"></i>
                        </div>

                        <h2 class="text-lg sm:text-xl font-semibold text-white mb-2">
                            Ready to Scan
                        </h2>
                        <p class="text-sm text-slate-400 text-center max-w-sm">
                            Click the <span class="text-emerald-400 font-medium">QR</span> or
                            <span class="text-blue-400 font-medium">Barcode</span> button below to start scanning
                        </p>
                        <p class="text-xs text-slate-500 mt-3 text-center">
                            <i class="fas fa-info-circle mr-1"></i>
                            {{ isCheckIn ? 'Scan attendee passes to check them IN' : 'Scan attendee passes to check them OUT' }}
                        </p>

                        <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 mt-6 sm:mt-8 w-full sm:w-auto">
                            <button @click="toggleQrScanner"
                                    class="w-full sm:w-auto px-4 sm:px-6 py-2.5 sm:py-3 bg-emerald-600 hover:bg-emerald-700 rounded-xl font-medium transition-all transform hover:scale-105 shadow-lg shadow-emerald-600/20 flex items-center justify-center gap-2 text-white"
                                    :disabled="isLoading">
                                <i class="fas fa-qrcode text-lg"></i>
                                QR Scanner
                            </button>
                            <button @click="toggleBarcodeScanner"
                                    class="w-full sm:w-auto px-4 sm:px-6 py-2.5 sm:py-3 bg-blue-600 hover:bg-blue-700 rounded-xl font-medium transition-all transform hover:scale-105 shadow-lg shadow-blue-600/20 flex items-center justify-center gap-2 text-white"
                                    :disabled="isLoading">
                                <i class="fas fa-barcode text-lg"></i>
                                Barcode
                            </button>
                        </div>
                    </div>

                    <!-- Result panel -->
                    <div v-if="result" class="w-full max-w-md animate-slide-up">
                        <div class="rounded-2xl shadow-2xl overflow-hidden"
                             :class="statusStyles[result.status]?.bg ?? 'bg-slate-700'">

                            <div class="p-4 sm:p-6 text-center">
                                <div class="flex items-center justify-center mb-3 sm:mb-4">
                                    <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-white/10 backdrop-blur-sm flex items-center justify-center text-2xl sm:text-3xl">
                                        <i :class="['fas', statusStyles[result.status]?.icon || 'fa-info-circle']"></i>
                                    </div>
                                </div>

                                <p class="text-base sm:text-lg font-bold tracking-wide text-white">
                                    {{ statusStyles[result.status]?.label ?? result.status }}
                                </p>
                                <p class="text-xs sm:text-sm opacity-90 mt-1 text-white">{{ result.message }}</p>
                            </div>

                            <div v-if="result.attendee" class="bg-black/20 backdrop-blur-sm p-4 sm:p-6">
                                <div class="flex items-center gap-3 sm:gap-4">
                                    <div class="flex-shrink-0">
                                        <img v-if="result.attendee.photo_url"
                                             :src="result.attendee.photo_url"
                                             :alt="result.attendee.name"
                                             class="w-12 h-12 sm:w-16 sm:h-16 rounded-full object-cover border-2 border-white/30" />
                                        <div v-else
                                             class="w-12 h-12 sm:w-16 sm:h-16 rounded-full bg-white/10 flex items-center justify-center text-base sm:text-xl font-semibold border-2 border-white/30 text-white">
                                            {{ getInitials(result.attendee.name) }}
                                        </div>
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-white truncate text-sm sm:text-base">{{ result.attendee.name }}</p>
                                        <p class="text-xs sm:text-sm opacity-80 truncate text-white">{{ result.attendee.organisation }}</p>
                                        <p v-if="result.attendee.role_title"
                                           class="text-[10px] sm:text-xs opacity-70 uppercase tracking-wider mt-0.5 text-white">
                                            {{ result.attendee.role_title }}
                                        </p>
                                    </div>
                                </div>

                                <p v-if="result.current_status"
                                   class="text-xs opacity-60 mt-3 text-center text-white">
                                    Current status: <span class="font-medium">{{ result.current_status.replace('_', ' ') }}</span>
                                </p>

                                <div v-if="result.original_check_in"
                                     class="mt-3 sm:mt-4 p-2.5 sm:p-3 bg-black/20 rounded-lg text-xs sm:text-sm">
                                    <p class="font-medium text-xs opacity-80 text-white">Original Check-in:</p>
                                    <div class="text-xs opacity-70 mt-1 space-y-0.5 text-white">
                                        <p><i class="far fa-clock mr-1.5"></i>{{ result.original_check_in.timestamp }}</p>
                                        <p v-if="result.original_check_in.staff">
                                            <i class="fas fa-user-circle mr-1.5"></i>{{ result.original_check_in.staff }}
                                        </p>
                                        <p v-if="result.original_check_in.gate">
                                            <i class="fas fa-map-pin mr-1.5"></i>{{ result.original_check_in.gate }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="p-3 sm:p-4 bg-black/30 backdrop-blur-sm space-y-2">
                                <button v-if="result.status === 'already_checked_in'"
                                        @click="allowOverride"
                                        class="w-full bg-white/10 hover:bg-white/20 text-white font-semibold rounded-xl py-2.5 sm:py-3 transition-all flex items-center justify-center gap-2 text-sm sm:text-base"
                                        :disabled="isLoading">
                                    <span v-if="isLoading" class="animate-spin inline-block">⟳</span>
                                    <i class="fas fa-check-double"></i>
                                    Allow anyway (staff override)
                                </button>

                                <button @click="scanNext"
                                        class="w-full bg-white text-slate-900 font-semibold rounded-xl py-2.5 sm:py-3 transition-all hover:bg-slate-100 flex items-center justify-center gap-2 text-sm sm:text-base"
                                        :disabled="isLoading">
                                    <i class="fas fa-sync-alt"></i>
                                    Scan next
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div v-if="isLoading" class="fixed inset-0 bg-black/80 backdrop-blur-sm flex flex-col items-center justify-center z-50">
            <div class="relative">
                <div class="w-16 h-16 border-4 border-emerald-500/30 border-t-emerald-500 rounded-full animate-spin"></div>
                <div class="absolute inset-0 flex items-center justify-center">
                    <i class="fas fa-qrcode text-emerald-400 text-2xl animate-pulse"></i>
                </div>
            </div>
            <p class="mt-6 text-white font-medium">{{ loadingMessage }}</p>
        </div>

        <!-- Toast -->
        <div v-if="showSuccessToast"
             class="fixed top-4 sm:top-20 right-4 left-4 sm:left-auto max-w-sm w-full px-4 py-3 rounded-xl shadow-2xl z-50 flex items-center gap-3 animate-slide-in-right"
             :class="{
                 'bg-emerald-600': toastType === 'success',
                 'bg-rose-600': toastType === 'error',
                 'bg-amber-500': toastType === 'warning'
             }">
            <i class="fas text-lg text-white" :class="{
                'fa-check-circle': toastType === 'success',
                'fa-exclamation-circle': toastType === 'error',
                'fa-exclamation-triangle': toastType === 'warning'
            }"></i>
            <span class="text-sm font-medium text-white flex-1">{{ successMessage }}</span>
            <button @click="showSuccessToast = false" class="text-white/70 hover:text-white transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- QR Scanner Modal -->
        <div v-if="showQrScanner" class="fixed inset-0 bg-black/95 z-40 flex items-center justify-center p-4">
            <div class="relative w-full max-w-lg">
                <div class="bg-black rounded-2xl overflow-hidden shadow-2xl border border-slate-700">
                    <qrcode-stream @detect="onDetect" @init="onInit" :camera="camera" :track="paintBoundingBox">
                        <div class="absolute inset-0 pointer-events-none">
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="relative w-48 h-48 sm:w-64 sm:h-64">
                                    <div class="absolute top-0 left-0 w-6 h-6 sm:w-8 sm:h-8 border-t-4 border-l-4 border-emerald-400"></div>
                                    <div class="absolute top-0 right-0 w-6 h-6 sm:w-8 sm:h-8 border-t-4 border-r-4 border-emerald-400"></div>
                                    <div class="absolute bottom-0 left-0 w-6 h-6 sm:w-8 sm:h-8 border-b-4 border-l-4 border-emerald-400"></div>
                                    <div class="absolute bottom-0 right-0 w-6 h-6 sm:w-8 sm:h-8 border-b-4 border-r-4 border-emerald-400"></div>
                                    <div class="absolute inset-x-0 top-1/2 h-0.5 bg-emerald-400/50 shadow-lg shadow-emerald-400/50 animate-scan-line"></div>
                                </div>
                            </div>
                            <div class="absolute bottom-4 sm:bottom-6 left-0 right-0 text-center text-slate-400 text-xs">
                                <i class="fas fa-camera mr-1.5"></i>
                                Position QR code within the frame
                            </div>
                        </div>
                    </qrcode-stream>
                </div>

                <button @click="toggleQrScanner"
                        class="absolute -bottom-14 sm:-bottom-16 left-1/2 -translate-x-1/2 px-4 sm:px-6 py-2 sm:py-2.5 bg-rose-600 hover:bg-rose-700 rounded-xl text-white font-medium transition-all shadow-lg shadow-rose-600/30 flex items-center gap-2 text-sm sm:text-base">
                    <i class="fas fa-times"></i>
                    Close Scanner
                </button>

                <div v-if="cameraError" class="absolute inset-0 flex items-center justify-center bg-black/90 rounded-2xl">
                    <div class="text-center px-6 max-w-sm">
                        <div class="w-12 h-12 sm:w-16 sm:h-16 rounded-full bg-rose-500/20 flex items-center justify-center mx-auto mb-3 sm:mb-4">
                            <i class="fas fa-exclamation-triangle text-rose-400 text-xl sm:text-2xl"></i>
                        </div>
                        <p class="text-rose-400 font-medium mb-2">Camera Error</p>
                        <p class="text-slate-400 text-xs sm:text-sm">{{ cameraError }}</p>
                        <button @click="retryCamera"
                                class="mt-4 px-4 py-2 bg-slate-700 hover:bg-slate-600 rounded-lg text-white transition-all text-sm">
                            <i class="fas fa-sync-alt mr-1.5"></i>
                            Retry
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Barcode Scanner Modal -->
        <div v-if="showBarcodeScanner" class="fixed inset-0 bg-black/95 z-40 flex items-center justify-center p-4">
            <div class="relative w-full max-w-lg">
                <div id="barcode-scanner" ref="scannerContainer"
                     class="bg-black rounded-2xl overflow-hidden shadow-2xl border border-slate-700"></div>

                <div class="absolute inset-0 pointer-events-none">
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="relative w-48 h-48 sm:w-64 sm:h-64">
                            <div class="absolute top-0 left-0 w-6 h-6 sm:w-8 sm:h-8 border-t-4 border-l-4 border-blue-400"></div>
                            <div class="absolute top-0 right-0 w-6 h-6 sm:w-8 sm:h-8 border-t-4 border-r-4 border-blue-400"></div>
                            <div class="absolute bottom-0 left-0 w-6 h-6 sm:w-8 sm:h-8 border-b-4 border-l-4 border-blue-400"></div>
                            <div class="absolute bottom-0 right-0 w-6 h-6 sm:w-8 sm:h-8 border-b-4 border-r-4 border-blue-400"></div>
                            <div class="absolute inset-x-0 top-1/2 h-0.5 bg-blue-400/50 shadow-lg shadow-blue-400/50 animate-scan-line"></div>
                        </div>
                    </div>
                    <div class="absolute bottom-4 sm:bottom-6 left-0 right-0 text-center text-slate-400 text-xs">
                        <i class="fas fa-barcode mr-1.5"></i>
                        Position barcode within the frame
                    </div>
                </div>

                <button @click="toggleBarcodeScanner"
                        class="absolute -bottom-14 sm:-bottom-16 left-1/2 -translate-x-1/2 px-4 sm:px-6 py-2 sm:py-2.5 bg-rose-600 hover:bg-rose-700 rounded-xl text-white font-medium transition-all shadow-lg shadow-rose-600/30 flex items-center gap-2 text-sm sm:text-base">
                    <i class="fas fa-times"></i>
                    Close Scanner
                </button>

                <div v-if="cameraError" class="absolute inset-0 flex items-center justify-center bg-black/90 rounded-2xl">
                    <div class="text-center px-6 max-w-sm">
                        <div class="w-12 h-12 sm:w-16 sm:h-16 rounded-full bg-rose-500/20 flex items-center justify-center mx-auto mb-3 sm:mb-4">
                            <i class="fas fa-exclamation-triangle text-rose-400 text-xl sm:text-2xl"></i>
                        </div>
                        <p class="text-rose-400 font-medium mb-2">Camera Error</p>
                        <p class="text-slate-400 text-xs sm:text-sm">{{ cameraError }}</p>
                        <button @click="retryCamera"
                                class="mt-4 px-4 py-2 bg-slate-700 hover:bg-slate-600 rounded-lg text-white transition-all text-sm">
                            <i class="fas fa-sync-alt mr-1.5"></i>
                            Retry
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
#barcode-scanner :deep(video) {
    width: 100% !important;
    height: auto !important;
    max-height: 70vh !important;
    object-fit: contain !important;
    border-radius: 1rem !important;
}

#barcode-scanner :deep(#qr-shaded-region) {
    border-color: rgba(59, 130, 246, 0.3) !important;
}

/* Animations */
@keyframes spin {
    to { transform: rotate(360deg); }
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

@keyframes scan-line {
    0% { top: 20%; opacity: 0; }
    50% { top: 80%; opacity: 1; }
    100% { top: 20%; opacity: 0; }
}

@keyframes slide-up {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes slide-in-right {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes slide-down {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-spin {
    animation: spin 1s linear infinite;
}

.animate-pulse {
    animation: pulse 2s ease-in-out infinite;
}

.animate-scan-line {
    animation: scan-line 2.5s ease-in-out infinite;
}

.animate-slide-up {
    animation: slide-up 0.4s ease-out forwards;
}

.animate-slide-in-right {
    animation: slide-in-right 0.3s ease-out forwards;
}

.animate-slide-down {
    animation: slide-down 0.2s ease-out forwards;
}

/* Scrollbar styling for dropdown */
.max-h-60::-webkit-scrollbar {
    width: 4px;
}

.max-h-60::-webkit-scrollbar-track {
    background: transparent;
}

.max-h-60::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 2px;
}

/* Responsive adjustments */
@media (max-width: 640px) {
    .container {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }
}
</style>
