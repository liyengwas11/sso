<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import { route } from "ziggy-js";

const form = useForm({
  email: "",
  password: "",
  remember: false,
});

function submit() {
  form.post(route("login"), {
    onFinish: () => form.reset("password"),
  });
}
</script>

<template>
  <Head title="Sign in" />

  <div class="min-h-screen flex items-center justify-center px-4 bg-slate-50">
    <div class="w-full max-w-sm">
      <h1 class="text-xl font-semibold text-slate-900 text-center mb-1">
        QR Attendance
      </h1>
      <p class="text-sm text-slate-500 text-center mb-6">Admin sign in</p>

      <div class="bg-white shadow-sm rounded-lg border border-slate-200 p-6">
        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label for="email" class="block text-sm font-medium text-slate-700 mb-1">
              Email
            </label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              required
              autofocus
              autocomplete="username"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
            />
            <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">
              {{ form.errors.email }}
            </p>
          </div>

          <div>
            <label for="password" class="block text-sm font-medium text-slate-700 mb-1">
              Password
            </label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              required
              autocomplete="current-password"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
            />
          </div>

          <label class="flex items-center gap-2 text-sm text-slate-600">
            <input
              v-model="form.remember"
              type="checkbox"
              class="rounded border-slate-300"
            />
            Remember me
          </label>

          <button
            type="submit"
            :disabled="form.processing"
            class="w-full bg-slate-900 text-white text-sm font-medium rounded-md py-2 hover:bg-slate-800 transition disabled:opacity-50"
          >
            {{ form.processing ? "Signing in…" : "Sign in" }}
          </button>
        </form>
      </div>

      <p class="text-xs text-slate-400 text-center mt-6">
        No self-registration — accounts are provisioned by an existing admin.
      </p>
    </div>
  </div>
</template>
