<template>
  <div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
      <div class="text-center">
        <h2 class="text-3xl font-extrabold text-gray-900">
          Sign in to your account
        </h2>
        <p class="mt-2 text-sm text-gray-600">
          Continue with Google to get started
        </p>
      </div>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
      <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
        <div class="space-y-6">
          <!-- Google Sign-in Button -->
          <div>
            <Link :href="route('google.redirect')" class="w-full flex justify-center items-center px-4 py-3 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-200">
              <svg class="h-5 w-5 mr-3" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12.48 10.92v3.28h7.84c-.24 1.84-.853 3.187-1.787 4.133-1.147 1.147-2.933 2.4-6.053 2.4-4.827 0-8.6-3.893-8.6-8.72s3.773-8.72 8.6-8.72c2.6 0 4.507 1.027 5.907 2.347l2.307-2.307C18.747 1.44 16.133 0 12.48 0 5.867 0 .307 5.387.307 12s5.56 12 12.173 12c3.573 0 6.267-1.173 8.373-3.36 2.16-2.16 2.84-5.213 2.84-7.667 0-.76-.053-1.467-.173-2.053H12.48z"/>
              </svg>
              Sign in with Google
            </Link>
          </div>

          <div class="relative">
            <div class="absolute inset-0 flex items-center">
              <div class="w-full border-t border-gray-300" />
            </div>
            <div class="relative flex justify-center text-sm">
              <span class="px-2 bg-white text-gray-500">Why Google?</span>
            </div>
          </div>

          <!-- Benefits -->
          <div class="space-y-3">
            <div class="flex items-start">
              <svg class="flex-shrink-0 h-5 w-5 text-green-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
              <p class="ml-3 text-sm text-gray-600">
                Secure authentication with your Google account
              </p>
            </div>
            <div class="flex items-start">
              <svg class="flex-shrink-0 h-5 w-5 text-green-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
              <p class="ml-3 text-sm text-gray-600">
                Direct access to YouTube API for video uploads
              </p>
            </div>
            <div class="flex items-start">
              <svg class="flex-shrink-0 h-5 w-5 text-green-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
              <p class="ml-3 text-sm text-gray-600">
                No need to create another password
              </p>
            </div>
          </div>
        </div>

        <div class="mt-6">
          <div class="text-center">
            <p class="text-xs text-gray-500">
              By signing in, you agree to our
              <a href="#" class="underline hover:text-gray-700">Terms of Service</a>
              and
              <a href="#" class="underline hover:text-gray-700">Privacy Policy</a>
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Back to Welcome -->
    <div class="mt-8 text-center">
      <Link :href="route('welcome')" class="text-indigo-600 hover:text-indigo-500 text-sm font-medium">
        ← Back to homepage
      </Link>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
</script>
