<script setup>
import {useForm} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
defineProps({canGenerateScript:Boolean, photos:{type:Array,default:()=>[]}});
const form=useForm({topic:'', duration:30, custom_script:'', photo_id:'', photo:null});
function selectFile(event){form.photo=event.target.files[0]||null;if(form.photo)form.photo_id='';}
</script>
<template>
  <AuthenticatedLayout>
    <main class="max-w-2xl mx-auto p-6 space-y-5">
      <h1 class="text-2xl font-bold">Criar vídeo</h1>
      <a href="/media" class="block rounded bg-indigo-700 text-white p-4">Usar minhas fotos e vídeos (incluindo iPhone)</a>
      <a href="/integrations/elevenlabs" class="block text-indigo-700 underline">Conectar ElevenLabs e escolher voz</a>
      <p>Crie um vídeo narrado em português. Sua foto recebe zoom suave em formato vertical; sem foto, usamos clipes de banco em formato horizontal.</p>
      <p class="text-sm text-gray-600">Este modo não anima a pessoa da foto nem cria movimentos corporais com IA.</p>
      <form @submit.prevent="form.post(route('videos.store'))" class="space-y-5">
        <label class="block">Foto (opcional)
          <input type="file" accept="image/jpeg,image/png" @change="selectFile" class="block w-full border rounded p-3 bg-white" />
          <span class="text-sm text-gray-600">JPG ou PNG, até 5 MB. Você pode escolher uma foto direto do celular.</span>
        </label>
        <fieldset v-if="photos.length && !form.photo">
          <legend class="mb-2">Ou escolha uma foto já enviada</legend>
          <div class="flex gap-3 overflow-x-auto pb-2">
            <label v-for="(photo,index) in photos" :key="photo.id" class="shrink-0 cursor-pointer rounded border-2 p-1" :class="form.photo_id===photo.id?'border-indigo-700':'border-gray-200'">
              <img :src="'/photos/'+photo.id" :alt="'Foto '+(index+1)" class="h-24 w-20 object-cover" />
              <input type="radio" v-model="form.photo_id" :value="photo.id" name="photo" /> Selecionar
            </label>
          </div>
          <button v-if="form.photo_id" type="button" @click="form.photo_id=''" class="text-indigo-700 underline">Usar clipes em vez desta foto</button>
        </fieldset>
        <label class="block">Tema
          <input v-model="form.topic" required maxlength="200" class="block w-full border rounded p-3" placeholder="Ex.: moda feminina" />
        </label>
        <label class="block">Duração aproximada
          <select v-model="form.duration" class="block w-full border rounded p-3"><option :value="30">30 segundos</option><option :value="60">1 minuto</option><option :value="120">2 minutos</option><option :value="300">5 minutos</option></select>
        </label>
        <label class="block">Texto da narração {{canGenerateScript?'(opcional)':'(obrigatório)'}}
          <textarea v-model="form.custom_script" :required="!canGenerateScript" maxlength="8000" rows="7" class="block w-full border rounded p-3" placeholder="Cole aqui o roteiro que será lido no vídeo." />
        </label>
        <p v-if="!canGenerateScript" class="rounded bg-indigo-50 p-3 text-sm">Peça um roteiro ao ChatGPT e cole acima. A criação automática de roteiro ainda não está configurada; com o texto preenchido, você pode gerar normalmente.</p>
        <p v-else class="text-sm">Se deixar o texto vazio, a IA criará a narração.</p>
        <p class="text-sm">A duração final acompanha a narração. Este modo não inclui música nem legendas automáticas.</p>
        <p v-for="(error,key) in form.errors" :key="key" role="alert" class="text-red-700">{{error}}</p>
        <p v-if="form.progress" role="status">Enviando foto: {{form.progress.percentage}}%</p>
        <button :disabled="form.processing" class="bg-indigo-700 text-white rounded px-5 py-3 disabled:opacity-50">{{form.processing?'Enviando…':'Gerar vídeo · 1 cota'}}</button>
      </form>
    </main>
  </AuthenticatedLayout>
</template>
