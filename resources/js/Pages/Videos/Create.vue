<script setup>
import {useForm, Link} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
defineProps({youtubeChannels:Array});
const form=useForm({topic:'', duration:60, custom_script:'', youtube_channel_id:null, photo_id:''});
</script>
<template><AuthenticatedLayout><main class="max-w-2xl mx-auto p-6 space-y-5"><h1 class="text-2xl font-bold">Criar vídeo</h1><p>Vídeo com narração em português: vertical com foto própria e zoom suave, ou horizontal com clipes de banco. Revise o resultado antes de publicar.</p>
<p><a href="/photos" class="text-indigo-700 underline">Enviar uma foto para o vídeo</a></p>
<form @submit.prevent="form.post(route('videos.store'))" class="space-y-5">
<label class="block">ID da foto (opcional)<input v-model="form.photo_id" class="block w-full border rounded p-3" placeholder="Copie o ID após enviar em Suas fotos" /></label>
<label class="block">Tema<input v-model="form.topic" required maxlength="200" class="block w-full border rounded p-3" placeholder="Escavadeiras trabalhando" /></label>
<label class="block">Duração aproximada<select v-model="form.duration" class="block w-full border rounded p-3"><option :value="30">30 segundos</option><option :value="60">1 minuto</option><option :value="120">2 minutos</option><option :value="300">5 minutos</option></select></label>
<label class="block">Roteiro próprio (opcional)<textarea v-model="form.custom_script" maxlength="8000" rows="7" class="block w-full border rounded p-3" /></label><p class="text-sm">Sem roteiro, a IA cria a narração. A duração final depende do texto. Não inclui música nem legendas automáticas.</p>
<p v-for="(error,key) in form.errors" :key="key" class="text-red-700">{{error}}</p>
<button :disabled="form.processing" class="bg-indigo-700 text-white rounded px-5 py-3 disabled:opacity-50">{{form.processing?'Enviando…':'Gerar vídeo'}}</button></form></main></AuthenticatedLayout></template>
