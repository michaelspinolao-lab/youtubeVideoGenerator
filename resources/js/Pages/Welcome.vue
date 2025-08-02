<template>
  <div class="min-h-screen bg-gradient-to-br from-indigo-900 via-purple-900 to-pink-900">
    <!-- Navigation -->
    <nav class="relative z-10">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-6">
          <div class="flex items-center">
            <h1 class="text-2xl font-bold text-white">YouTube Video Generator</h1>
          </div>
          <div class="flex items-center space-x-4">
            <Link :href="route('demo.login')" class="bg-white text-gray-900 hover:bg-gray-100 px-4 py-2 rounded-lg font-medium transition duration-200">
              Try Demo
            </Link>
          </div>
        </div>
      </div>
    </nav>

    <!-- Hero Section -->
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-16">
      <div class="text-center">
        <h1 class="text-4xl md:text-6xl font-bold text-white mb-6">
          Create YouTube Videos
          <span class="text-transparent bg-clip-text bg-gradient-to-r from-pink-400 to-yellow-400">
            with AI
          </span>
        </h1>
        <p class="text-xl text-gray-300 mb-8 max-w-3xl mx-auto">
          Generate engaging YouTube videos automatically using AI. From script writing to video assembly,
          we handle everything so you can focus on growing your channel.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <Link :href="route('google.redirect')" class="inline-flex items-center px-8 py-4 border border-transparent text-lg font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 transition duration-200">
            <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12.48 10.92v3.28h7.84c-.24 1.84-.853 3.187-1.787 4.133-1.147 1.147-2.933 2.4-6.053 2.4-4.827 0-8.6-3.893-8.6-8.72s3.773-8.72 8.6-8.72c2.6 0 4.507 1.027 5.907 2.347l2.307-2.307C18.747 1.44 16.133 0 12.48 0 5.867 0 .307 5.387.307 12s5.56 12 12.173 12c3.573 0 6.267-1.173 8.373-3.36 2.16-2.16 2.84-5.213 2.84-7.667 0-.76-.053-1.467-.173-2.053H12.48z"/>
            </svg>
            Start with Google
          </Link>
          <Link :href="route('demo.login')" class="inline-flex items-center px-8 py-4 border border-white text-lg font-medium rounded-lg text-white hover:bg-white hover:text-gray-900 transition duration-200">
            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
            </svg>
            Try Demo
          </Link>
          <a href="#features" class="inline-flex items-center px-8 py-4 border border-white text-lg font-medium rounded-lg text-white hover:bg-white hover:text-gray-900 transition duration-200">
            Learn More
          </a>
        </div>
      </div>

      <!-- Demo Video Placeholder -->
      <div class="mt-16 max-w-4xl mx-auto">
        <div class="relative">
          <div class="bg-gray-800 rounded-lg shadow-2xl aspect-video flex items-center justify-center">
            <div class="text-center">
              <svg class="h-16 w-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1.01M15 10h1.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <p class="text-gray-400">Demo video coming soon</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Features Section -->
    <div id="features" class="relative z-10 py-16 bg-black bg-opacity-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
          <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">
            Everything You Need to Create Amazing Videos
          </h2>
          <p class="text-xl text-gray-300 max-w-2xl mx-auto">
            Our AI-powered platform handles the entire video creation process from start to finish.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <!-- Feature 1 -->
          <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-6">
            <div class="flex items-center justify-center w-12 h-12 bg-indigo-600 rounded-lg mb-4">
              <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-white mb-2">AI Script Generation</h3>
            <p class="text-gray-300">
              Just provide a topic and our AI will generate engaging scripts with perfect pacing and structure.
            </p>
          </div>

          <!-- Feature 2 -->
          <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-6">
            <div class="flex items-center justify-center w-12 h-12 bg-purple-600 rounded-lg mb-4">
              <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-white mb-2">Auto Visual Selection</h3>
            <p class="text-gray-300">
              Automatically fetch relevant stock footage and images from Pexels based on your script content.
            </p>
          </div>

          <!-- Feature 3 -->
          <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-6">
            <div class="flex items-center justify-center w-12 h-12 bg-pink-600 rounded-lg mb-4">
              <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-white mb-2">AI Voiceover</h3>
            <p class="text-gray-300">
              Professional-quality text-to-speech narration that brings your scripts to life.
            </p>
          </div>

          <!-- Feature 4 -->
          <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-6">
            <div class="flex items-center justify-center w-12 h-12 bg-yellow-600 rounded-lg mb-4">
              <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-white mb-2">Auto Upload</h3>
            <p class="text-gray-300">
              Seamlessly upload finished videos directly to your YouTube channel with optimized titles and descriptions.
            </p>
          </div>

          <!-- Feature 5 -->
          <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-6">
            <div class="flex items-center justify-center w-12 h-12 bg-green-600 rounded-lg mb-4">
              <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-white mb-2">Autopilot Mode</h3>
            <p class="text-gray-300">
              Set it and forget it. Automatically generate and upload videos on your schedule.
            </p>
          </div>

          <!-- Feature 6 -->
          <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-6">
            <div class="flex items-center justify-center w-12 h-12 bg-blue-600 rounded-lg mb-4">
              <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-white mb-2">Analytics & Insights</h3>
            <p class="text-gray-300">
              Track your video performance and optimize your content strategy with detailed analytics.
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- CTA Section -->
    <div class="relative z-10 py-16">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">
          Ready to Transform Your YouTube Channel?
        </h2>
        <p class="text-xl text-gray-300 mb-8 max-w-2xl mx-auto">
          Join thousands of creators who are already using AI to scale their video production.
        </p>
        <Link :href="route('demo.login')" class="inline-flex items-center px-8 py-4 border border-transparent text-lg font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 transition duration-200">
          <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
          </svg>
          Try Demo Now
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
</script>
