<script setup>
import AdminLayout from "../../../Layouts/AdminLayout.vue";
import { router, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { ref } from "vue";

const props = defineProps({
    staff: Array,
    roles: Array,
});

const showModal = ref(false);
const editingStaff = ref(null);
const photoPreview = ref(null);

const form = useForm({
    name: "",
    email: "",
    password: "",
    roles: [],
    photo: null,
});

function openCreate() {
    editingStaff.value = null;
    form.reset();
    photoPreview.value = null;
    showModal.value = true;
}

function openEdit(staff) {
    editingStaff.value = staff;
    form.name = staff.name;
    form.email = staff.email;
    form.password = ""; // Don't pre-fill password
    form.roles = staff.roles.map(r => r.name);
    form.photo = null;
    photoPreview.value = staff.photo_url || null;
    showModal.value = true;
}

function handleFileUpload(event) {
    const file = event.target.files[0];
    if (file) {
        form.photo = file;
        // Create preview URL
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
    // Reset the file input
    const fileInput = document.getElementById('photo-input');
    if (fileInput) {
        fileInput.value = '';
    }
}

function toggleRole(name) {
    const idx = form.roles.indexOf(name);
    if (idx === -1) form.roles.push(name);
    else form.roles.splice(idx, 1);
}

function submit() {
    const opts = {
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            photoPreview.value = null;
            showModal.value = false;
        },
    };

    if (editingStaff.value) {
        form.transform((data) => ({ ...data, _method: "patch" }))
            .post(route("admin.staff.update", editingStaff.value.id), opts);
    } else {
        form.post(route("admin.staff.store"), opts);
    }
}

function removeStaff(member) {
    if (!confirm(`Remove ${member.name}'s account? They won't be able to log in anymore.`)) return;
    router.delete(route("admin.staff.destroy", member.id));
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
</script>

<template>
    <AdminLayout>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">Staff</h1>
                <p class="text-sm text-slate-500">Everyone who can log in — admins and gate staff.</p>
            </div>
            <button @click="openCreate"
                class="bg-slate-900 text-white text-sm font-medium rounded-md px-4 py-2 hover:bg-slate-800">
                Add staff account
            </button>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Photo</th>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Email</th>
                        <th class="px-4 py-3 font-medium">Roles</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="member in staff" :key="member.id">
                        <td class="px-4 py-3">
                            <img v-if="member.photo_url" :src="member.photo_url" :alt="member.name"
                                class="w-10 h-10 rounded-full object-cover" />
                            <div v-else
                                class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center text-slate-600 text-sm font-medium">
                                {{ getInitials(member.name) }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-900">{{ member.name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ member.email }}</td>
                        <td class="px-4 py-3">
                            <span v-for="role in member.roles" :key="role.id"
                                class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full mr-1">
                                {{ role.name }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <button @click="openEdit(member)"
                                class="text-sm text-slate-500 hover:text-slate-900">Edit</button>
                            <button @click="removeStaff(member)"
                                class="text-sm text-red-500 hover:text-red-700">Remove</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Create/Edit modal -->
        <div v-if="showModal" class="fixed inset-0 bg-black/30 flex items-center justify-center px-4 z-50">
            <div class="bg-white rounded-lg w-full max-w-sm p-6 max-h-[90vh] overflow-y-auto">
                <h2 class="font-semibold text-slate-900 mb-4">
                    {{ editingStaff ? "Edit staff account" : "Add staff account" }}
                </h2>
                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Photo Upload -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Profile photo</label>

                        <!-- Current photo preview -->
                        <div v-if="photoPreview" class="mb-2">
                            <img :src="photoPreview" alt="Profile preview"
                                class="w-20 h-20 rounded-full object-cover border-2 border-slate-200" />
                        </div>

                        <div class="flex items-center gap-4">
                            <div class="flex-1">
                                <input id="photo-input" type="file" accept="image/*" @change="handleFileUpload"
                                    class="text-sm w-full" />
                            </div>
                            <button v-if="photoPreview" type="button" @click="removePhoto"
                                class="text-sm text-red-500 hover:text-red-700">
                                Remove
                            </button>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Recommended: Square image, max 2MB</p>
                        <p v-if="form.errors.photo" class="mt-1 text-sm text-red-600">{{ form.errors.photo }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                        <input v-model="form.name" type="text" required
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                        <input v-model="form.email" type="email" required
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                        <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            {{ editingStaff ? "New password (optional)" : "Initial password" }}
                        </label>
                        <input v-model="form.password" type="text" :required="!editingStaff"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                        <p class="text-xs text-slate-400 mt-1">
                            {{ editingStaff ? "Leave blank to keep current password" : "Share this with them directly"
                            }}
                        </p>
                        <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Roles</label>
                        <div class="space-y-1.5">
                            <label v-for="role in roles" :key="role.id"
                                class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" :checked="form.roles.includes(role.name)"
                                    @change="toggleRole(role.name)" class="rounded border-slate-300" />
                                {{ role.name }}
                            </label>
                        </div>
                        <p v-if="form.errors.roles" class="mt-1 text-sm text-red-600">{{ form.errors.roles }}</p>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button type="button" @click="showModal = false"
                            class="flex-1 border border-slate-300 text-slate-700 text-sm font-medium rounded-md py-2 hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit" :disabled="form.processing"
                            class="flex-1 bg-slate-900 text-white text-sm font-medium rounded-md py-2 hover:bg-slate-800 disabled:opacity-50">
                            {{ editingStaff ? "Update" : "Save" }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
