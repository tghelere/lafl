<script setup lang="ts">
import Link from '@tiptap/extension-link'
import StarterKit from '@tiptap/starter-kit'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import { onBeforeUnmount, ref, watch } from 'vue'

import { MediaFigure, type MediaFigureAttrs } from '@/components/editor/mediaFigure'
import MediaPickerDialog from '@/components/MediaPickerDialog.vue'

/**
 * Editor de conteúdo das páginas do site. A barra oferece exatamente o que a allowlist do
 * backend aceita (App\Support\Html\ContentSanitizer, ver
 * docs/decisoes/0010-html-do-cms-sanitizado-no-backend.md): parágrafo, h2, h3, negrito,
 * itálico, link, lista com e sem ordem, e imagem da biblioteca com legenda (ver
 * src/components/editor/mediaFigure.ts).
 *
 * Oferecer formatação além disso seria pior que não oferecer: o editor mostraria a
 * formatação aplicada e o backend a removeria ao salvar, sem a pessoa entender por quê. Quem
 * decide o que é válido continua sendo o backend — aqui é só coerência de interface.
 */
const props = defineProps<{
  modelValue: string
  ariaLabel?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit.configure({
      // Nós e marcas fora da allowlist do backend ficam desligados — o que o editor não
      // sabe criar, ninguém cria sem querer por atalho de teclado ou colagem.
      heading: { levels: [2, 3] },
      codeBlock: false,
      blockquote: false,
      horizontalRule: false,
      code: false,
      strike: false,
      link: false,
    }),
    Link.configure({
      openOnClick: false,
      // Sem autolink: transformar texto em link enquanto a pessoa digita surpreende e é
      // difícil de desfazer no meio de uma frase.
      autolink: false,
      // O padrão da extensão é marcar TODO link com target="_blank" e
      // rel="noopener noreferrer nofollow" — um link interno ("/transparencia") passaria a
      // abrir em aba nova sem ninguém ter pedido, e `nofollow` num link para o próprio site
      // atrapalha a indexação. Estes são só os valores padrão de link novo: `target` e `rel`
      // que já existem no HTML são atributos parseados da marca e continuam preservados. Quem
      // decide o `rel` final é o backend (externo recebe rel de segurança, interno não).
      HTMLAttributes: { target: null, rel: null },
    }),
    MediaFigure,
  ],
  editorProps: {
    handleDrop: (view, event, _slice, moved) => {
      // `moved`: arrasto interno (uma figura mudando de lugar), que o ProseMirror já resolve.
      const file = moved ? null : (event.dataTransfer?.files?.[0] ?? null)

      if (!file) {
        return false
      }

      event.preventDefault()
      openUploadForDrop(file, view.posAtCoords({ left: event.clientX, top: event.clientY })?.pos ?? null)

      return true
    },
    attributes: {
      class: 'rich-text__surface',
      ...(props.ariaLabel ? { 'aria-label': props.ariaLabel } : {}),
    },
  },
  onUpdate: ({ editor: instance }) => {
    emit('update:modelValue', instance.getHTML())
  },
})

// Recarregar a página no formulário (trocar de registro, descartar alterações) precisa
// refletir no editor; sem a comparação com getHTML() o cursor saltaria para o fim a cada
// tecla, porque o próprio onUpdate acabou de emitir esse mesmo valor.
watch(
  () => props.modelValue,
  (value) => {
    if (editor.value && value !== editor.value.getHTML()) {
      editor.value.commands.setContent(value, { emitUpdate: false })
    }
  },
)

onBeforeUnmount(() => {
  editor.value?.destroy()
})

const pickerOpen = ref(false)
const editingFigure = ref<MediaFigureAttrs | null>(null)
/** A foto solta no corpo do texto, e onde ela foi solta. `null` = o diálogo abriu pelo botão. */
const droppedFile = ref<File | null>(null)
const dropPosition = ref<number | null>(null)

/**
 * Arrastar uma foto do computador e soltar no corpo do texto: abre o mesmo diálogo de envio do
 * botão "Imagem", já com a foto escolhida — a mesma validação e a mesma declaração obrigatória,
 * porque o envio é o mesmo. A foto entra onde foi solta (onde o cursor de arrasto aparece), e
 * não onde estava o cursor de digitação.
 *
 * Um arquivo por vez: de vários soltos juntos, só o primeiro é usado. Um arquivo que não é
 * foto também abre o diálogo, com a explicação, em vez de sumir em silêncio.
 */
function openUploadForDrop(file: File, position: number | null): void {
  editingFigure.value = null
  droppedFile.value = file
  dropPosition.value = position
  pickerOpen.value = true
}

/**
 * Com uma figura selecionada, o botão edita o alternativo e a legenda dela; sem, insere uma
 * imagem nova onde está o cursor.
 */
function openImagePicker(): void {
  if (!editor.value) {
    return
  }

  editingFigure.value = editor.value.isActive('mediaFigure')
    ? (editor.value.getAttributes('mediaFigure') as MediaFigureAttrs)
    : null
  droppedFile.value = null
  dropPosition.value = null
  pickerOpen.value = true
}

function applyImage(attrs: MediaFigureAttrs): void {
  if (!editor.value) {
    return
  }

  if (editingFigure.value) {
    editor.value.chain().focus().updateAttributes('mediaFigure', { alt: attrs.alt, caption: attrs.caption }).run()
  } else if (dropPosition.value !== null) {
    editor.value.chain().focus().insertContentAt(dropPosition.value, { type: 'mediaFigure', attrs }).run()
  } else {
    editor.value.chain().focus().insertContent({ type: 'mediaFigure', attrs }).run()
  }
}

function closePicker(): void {
  pickerOpen.value = false
  droppedFile.value = null
  dropPosition.value = null
}

function toggleLink(): void {
  if (!editor.value) {
    return
  }

  if (editor.value.isActive('link')) {
    editor.value.chain().focus().unsetLink().run()

    return
  }

  const href = window.prompt(
    'Endereço do link (ex.: /transparencia para uma página do site, ou https://... para fora)',
    '',
  )

  if (href === null || href.trim() === '') {
    return
  }

  editor.value.chain().focus().setLink({ href: href.trim() }).run()
}
</script>

<template>
  <div class="rich-text">
    <div
      v-if="editor"
      class="rich-text__toolbar"
      role="toolbar"
      aria-label="Formatação do conteúdo"
    >
      <button
        type="button"
        class="rich-text__button"
        :class="{ 'rich-text__button--active': editor.isActive('paragraph') }"
        :aria-pressed="editor.isActive('paragraph')"
        @click="editor.chain().focus().setParagraph().run()"
      >
        Parágrafo
      </button>
      <button
        type="button"
        class="rich-text__button"
        :class="{ 'rich-text__button--active': editor.isActive('heading', { level: 2 }) }"
        :aria-pressed="editor.isActive('heading', { level: 2 })"
        @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
      >
        Título
      </button>
      <button
        type="button"
        class="rich-text__button"
        :class="{ 'rich-text__button--active': editor.isActive('heading', { level: 3 }) }"
        :aria-pressed="editor.isActive('heading', { level: 3 })"
        @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
      >
        Subtítulo
      </button>

      <span
        class="rich-text__separator"
        aria-hidden="true"
      />

      <button
        type="button"
        class="rich-text__button"
        :class="{ 'rich-text__button--active': editor.isActive('bold') }"
        :aria-pressed="editor.isActive('bold')"
        @click="editor.chain().focus().toggleBold().run()"
      >
        <strong>N</strong>
      </button>
      <button
        type="button"
        class="rich-text__button"
        :class="{ 'rich-text__button--active': editor.isActive('italic') }"
        :aria-pressed="editor.isActive('italic')"
        @click="editor.chain().focus().toggleItalic().run()"
      >
        <em>I</em>
      </button>
      <button
        type="button"
        class="rich-text__button"
        :class="{ 'rich-text__button--active': editor.isActive('link') }"
        :aria-pressed="editor.isActive('link')"
        @click="toggleLink"
      >
        {{ editor.isActive('link') ? 'Remover link' : 'Link' }}
      </button>

      <span
        class="rich-text__separator"
        aria-hidden="true"
      />

      <button
        type="button"
        class="rich-text__button"
        :class="{ 'rich-text__button--active': editor.isActive('bulletList') }"
        :aria-pressed="editor.isActive('bulletList')"
        @click="editor.chain().focus().toggleBulletList().run()"
      >
        Lista
      </button>
      <button
        type="button"
        class="rich-text__button"
        :class="{ 'rich-text__button--active': editor.isActive('orderedList') }"
        :aria-pressed="editor.isActive('orderedList')"
        @click="editor.chain().focus().toggleOrderedList().run()"
      >
        Lista numerada
      </button>

      <span
        class="rich-text__separator"
        aria-hidden="true"
      />

      <button
        type="button"
        class="rich-text__button"
        :class="{ 'rich-text__button--active': editor.isActive('mediaFigure') }"
        @click="openImagePicker"
      >
        {{ editor.isActive('mediaFigure') ? 'Editar imagem' : 'Imagem' }}
      </button>
    </div>

    <EditorContent
      :editor="editor"
      class="rich-text__content"
    />

    <MediaPickerDialog
      :open="pickerOpen"
      :editing="editingFigure"
      :initial-file="droppedFile"
      @confirm="applyImage"
      @close="closePicker"
    />
  </div>
</template>
