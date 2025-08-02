<template>
  <AuthenticatedLayout>
    <div class="py-12">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-2xl font-bold text-gray-900">Generate New Video</h2>
              <p class="mt-1 text-sm text-gray-600">
                Create a new AI-generated video for your YouTube channel
              </p>
            </div>
            <Link :href="route('videos.index')" class="text-indigo-600 hover:text-indigo-500 text-sm font-medium">
              ← Back to Videos
            </Link>
          </div>
        </div>

        <!-- Form -->
        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
          <form @submit.prevent="submit" class="p-6 space-y-6">
            <!-- Topic -->
            <div>
              <label for="topic" class="block text-sm font-medium text-gray-700 mb-2">
                Video Topic <span class="text-red-500">*</span>
              </label>
              <input
                id="topic"
                v-model="form.topic"
                type="text"
                required
                placeholder="e.g., How to make the perfect cup of coffee"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                :class="{ 'border-red-300': errors.topic }"
              />
              <p v-if="errors.topic" class="mt-1 text-sm text-red-600">{{ errors.topic }}</p>
              <p class="mt-1 text-sm text-gray-500">
                Describe what you want your video to be about. Be specific for better results.
              </p>
            </div>

            <!-- Title -->
            <div>
              <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                Video Title
              </label>
              <input
                id="title"
                v-model="form.title"
                type="text"
                placeholder="Leave blank to auto-generate based on topic"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                :class="{ 'border-red-300': errors.title }"
              />
              <p v-if="errors.title" class="mt-1 text-sm text-red-600">{{ errors.title }}</p>
              <p class="mt-1 text-sm text-gray-500">
                If left blank, we'll generate an engaging title based on your topic.
              </p>
            </div>

            <!-- Description -->
            <div>
              <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                Video Description
              </label>
              <textarea
                id="description"
                v-model="form.description"
                rows="4"
                placeholder="Leave blank to auto-generate based on topic"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                :class="{ 'border-red-300': errors.description }"
              ></textarea>
              <p v-if="errors.description" class="mt-1 text-sm text-red-600">{{ errors.description }}</p>
              <p class="mt-1 text-sm text-gray-500">
                If left blank, we'll generate a detailed description based on your topic.
              </p>
            </div>

            <!-- Duration -->
            <div>
              <label for="target_duration" class="block text-sm font-medium text-gray-700 mb-2">
                Target Duration
              </label>
              <select
                id="target_duration"
                v-model="form.target_duration"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                :class="{ 'border-red-300': errors.target_duration }"
              >
                <option value="30">30 seconds</option>
                <option value="60">1 minute</option>
                <option value="120">2 minutes</option>
                <option value="180">3 minutes</option>
                <option value="300">5 minutes</option>
                <option value="600">10 minutes</option>
              </select>
              <p v-if="errors.target_duration" class="mt-1 text-sm text-red-600">{{ errors.target_duration }}</p>
              <p class="mt-1 text-sm text-gray-500">
                How long should your video be? We'll adjust the script accordingly.
              </p>
            </div>

            <!-- Voice Settings -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-4">Voice Settings</label>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Voice Type -->
                <div>
                  <label for="voice_type" class="block text-sm font-medium text-gray-600 mb-2">
                    Voice Type
                  </label>
                  <select
                    id="voice_type"
                    v-model="form.voice_type"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  >
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                  </select>
                </div>

                <!-- Voice Speed -->
                <div>
                  <label for="voice_speed" class="block text-sm font-medium text-gray-600 mb-2">
                    Speaking Speed
                  </label>
                  <select
                    id="voice_speed"
                    v-model="form.voice_speed"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  >
                    <option value="slow">Slow</option>
                    <option value="normal">Normal</option>
                    <option value="fast">Fast</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- YouTube Channel -->
            <div v-if="youtubeChannels.length > 0">
              <label for="youtube_channel_id" class="block text-sm font-medium text-gray-700 mb-2">
                Upload to YouTube Channel
              </label>
              <select
                id="youtube_channel_id"
                v-model="form.youtube_channel_id"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                :class="{ 'border-red-300': errors.youtube_channel_id }"
              >
                <option value="">Don't upload (save as draft)</option>
                <option v-for="channel in youtubeChannels" :key="channel.id" :value="channel.id">
                  {{ channel.channel_name }} ({{ channel.subscriber_count }} subscribers)
                </option>
              </select>
              <p v-if="errors.youtube_channel_id" class="mt-1 text-sm text-red-600">{{ errors.youtube_channel_id }}</p>
              <p class="mt-1 text-sm text-gray-500">
                Select a channel to automatically upload the video when generation is complete.
              </p>
            </div>

            <!-- No YouTube Channels Message -->
            <div v-else class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
              <div class="flex">
                <svg class="h-5 w-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
                <div class="ml-3">
                  <h3 class="text-sm font-medium text-yellow-800">
                    No YouTube channels connected
                  </h3>
                  <div class="mt-2 text-sm text-yellow-700">
                    <p>Your video will be saved as a draft. You can upload it manually later or connect a YouTube channel first.</p>
                  </div>
                  <div class="mt-4">
                    <Link :href="route('dashboard')" class="text-sm font-medium text-yellow-800 underline hover:text-yellow-600">
                      Connect YouTube Channel →
                    </Link>
                  </div>
                </div>
              </div>
            </div>

            <!-- Advanced Options -->
            <div>
              <button
                type="button"
                @click="showAdvanced = !showAdvanced"
                class="flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-500"
              >
                <svg
                  :class="{ 'rotate-90': showAdvanced }"
                  class="h-4 w-4 mr-1 transform transition-transform"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
                Advanced Options
              </button>

              <div v-show="showAdvanced" class="mt-4 space-y-4 p-4 bg-gray-50 rounded-lg">
                <!-- Include Captions -->
                <div class="flex items-center">
                  <input
                    id="include_captions"
                    v-model="form.include_captions"
                    type="checkbox"
                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                  />
                  <label for="include_captions" class="ml-2 block text-sm text-gray-700">
                    Include captions/subtitles
                  </label>
                </div>

                <!-- Custom Script -->
                <div>
                  <label for="custom_script" class="block text-sm font-medium text-gray-700 mb-2">
                    Custom Script (Optional)
                  </label>
                  <textarea
                    id="custom_script"
                    v-model="form.custom_script"
                    rows="6"
                    placeholder="Write your own script or leave blank for AI generation"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  ></textarea>
                  <p class="mt-1 text-sm text-gray-500">
                    If provided, we'll use your script instead of generating one. Make sure it matches your target duration.
                  </p>
                </div>

                <!-- Keywords -->
                <div>
                  <label for="keywords" class="block text-sm font-medium text-gray-700 mb-2">
                    Keywords (Optional)
                  </label>
                  <input
                    id="keywords"
                    v-model="form.keywords"
                    type="text"
                    placeholder="coffee, brewing, tutorial (comma-separated)"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  />
                  <p class="mt-1 text-sm text-gray-500">
                    Keywords to focus on for better visual matching and SEO.
                  </p>
                </div>
              </div>
            </div>

            <!-- Submit Buttons -->
            <div class="flex items-center justify-between pt-6 border-t border-gray-200">
              <div class="text-sm text-gray-500">
                <p>Generation typically takes 5-10 minutes.</p>
                <p>You'll be notified when your video is ready.</p>
              </div>

              <div class="flex items-center space-x-3">
                <Link :href="route('videos.index')" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                  Cancel
                </Link>
                <button
                  type="submit"
                  :disabled="processing"
                  class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <svg v-if="processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  {{ processing ? 'Generating...' : 'Generate Video' }}
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  youtubeChannels: {
    type: Array,
    default: () => []
  }
})

const showAdvanced = ref(false)

const { data: form, post, processing, errors } = useForm({
  topic: '',
  title: '',
  description: '',
  target_duration: 120,
  voice_type: 'female',
  voice_speed: 'normal',
  youtube_channel_id: '',
  include_captions: true,
  custom_script: '',
  keywords: ''
})

const submit = () => {
  post(route('videos.store'))
}
</script>
