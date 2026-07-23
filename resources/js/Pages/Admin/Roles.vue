<script setup>
import AdminLayout from "../../Layouts/AdminLayout.vue";
import { router, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { ref } from "vue";

const props = defineProps({
  roles: Array,
  permissions: Array,
});

const showModal = ref(false);
const editingRole = ref(null);

const form = useForm({
  name: "",
  permissions: [],
});

function openCreate() {
  editingRole.value = null;
  form.reset();
  form.permissions = [];
  form.clearErrors();
  showModal.value = true;
}

function openEdit(role) {
  editingRole.value = role;
  form.name = role.name;
  form.permissions = role.permissions.map((p) => p.name);
  form.clearErrors();
  showModal.value = true;
}

function togglePermission(name) {
  const idx = form.permissions.indexOf(name);
  if (idx === -1) form.permissions.push(name);
  else form.permissions.splice(idx, 1);
}

function submit() {
  if (editingRole.value) {
    form.patch(route("admin.roles.update", editingRole.value.id), {
      onSuccess: () => (showModal.value = false),
    });
  } else {
    form.post(route("admin.roles.store"), {
      onSuccess: () => (showModal.value = false),
    });
  }
}

function destroyRole(role) {
  if (!confirm(`Delete the "${role.name}" role?`)) return;
  router.delete(route("admin.roles.destroy", role.id), { preserveScroll: true });
}

// Inline "new permission" form, since it's only ever created in the
// context of building a role — no separate permissions page.
const newPermission = useForm({ name: "" });
function addPermission() {
  newPermission.post(route("admin.permissions.store"), {
    preserveScroll: true,
    onSuccess: () => newPermission.reset(),
  });
}
</script>

<template>
  <AdminLayout>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-semibold text-slate-900">Roles & Permissions</h1>
        <p class="text-sm text-slate-500">
          Changes here take effect immediately — no deployment required.
        </p>
      </div>
      <button
        @click="openCreate"
        class="bg-slate-900 text-white text-sm font-medium rounded-md px-4 py-2 hover:bg-slate-800 transition"
      >
        Create role
      </button>
    </div>

    <div class="grid gap-4 mb-8">
      <div
        v-for="role in roles"
        :key="role.id"
        class="bg-white rounded-lg border border-slate-200 p-5 flex items-start justify-between"
      >
        <div>
          <p class="font-medium text-slate-900">{{ role.name }}</p>
          <div class="flex flex-wrap gap-1.5 mt-2">
            <span
              v-for="perm in role.permissions"
              :key="perm.id"
              class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full"
            >
              {{ perm.name }}
            </span>
            <span v-if="role.permissions.length === 0" class="text-xs text-slate-400 italic">
              No permissions assigned
            </span>
          </div>
        </div>
        <div class="flex gap-3 shrink-0 ml-4">
          <button @click="openEdit(role)" class="text-sm text-slate-500 hover:text-slate-900">Edit</button>
          <button
            v-if="role.name !== 'admin'"
            @click="destroyRole(role)"
            class="text-sm text-red-500 hover:text-red-700"
          >
            Delete
          </button>
        </div>
      </div>
    </div>

    <!-- Permission catalogue: create new permissions here, used by the modal above -->
    <div class="bg-white rounded-lg border border-slate-200 p-5 max-w-md">
      <p class="font-medium text-slate-900 mb-3">Permission catalogue</p>
      <div class="flex flex-wrap gap-1.5 mb-4">
        <span
          v-for="perm in permissions"
          :key="perm.id"
          class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full"
        >
          {{ perm.name }}
        </span>
      </div>
      <form @submit.prevent="addPermission" class="flex gap-2">
        <input
          v-model="newPermission.name"
          type="text"
          placeholder="e.g. export-reports"
          class="flex-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
        />
        <button
          type="submit"
          :disabled="newPermission.processing"
          class="bg-slate-900 text-white text-sm font-medium rounded-md px-3 py-1.5 hover:bg-slate-800 disabled:opacity-50"
        >
          Add
        </button>
      </form>
      <p v-if="newPermission.errors.name" class="mt-1 text-sm text-red-600">
        {{ newPermission.errors.name }}
      </p>
    </div>

    <!-- Create/Edit role modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/30 flex items-center justify-center px-4 z-50">
      <div class="bg-white rounded-lg w-full max-w-sm p-6">
        <h2 class="font-semibold text-slate-900 mb-4">
          {{ editingRole ? "Edit role" : "Create role" }}
        </h2>

        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Role name</label>
            <input
              v-model="form.name"
              type="text"
              required
              :disabled="editingRole?.name === 'admin'"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 disabled:bg-slate-50"
            />
            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Permissions</label>
            <div class="space-y-1.5 max-h-48 overflow-y-auto">
              <label
                v-for="perm in permissions"
                :key="perm.id"
                class="flex items-center gap-2 text-sm text-slate-700"
              >
                <input
                  type="checkbox"
                  :checked="form.permissions.includes(perm.name)"
                  @change="togglePermission(perm.name)"
                  class="rounded border-slate-300"
                />
                {{ perm.name }}
              </label>
            </div>
          </div>

          <div class="flex gap-2 pt-2">
            <button
              type="button"
              @click="showModal = false"
              class="flex-1 border border-slate-300 text-slate-700 text-sm font-medium rounded-md py-2 hover:bg-slate-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="form.processing"
              class="flex-1 bg-slate-900 text-white text-sm font-medium rounded-md py-2 hover:bg-slate-800 disabled:opacity-50"
            >
              Save
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>
