import { Node } from '@tiptap/vue-3'

import { mediaPreviewUrl } from '@/services/media'

/** A forma canônica que a API aceita no conteúdo (App\Support\Media\MediaUrl). */
const CANONICAL_SRC = /^\/midia\/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/

export type MediaFigureAttrs = {
  uuid: string
  alt: string
  caption: string
}

/**
 * Imagem da biblioteca dentro do texto: `<figure><img src="/midia/{uuid}" alt><figcaption>`.
 *
 * Nó atômico — a imagem é um bloco só, que se seleciona, arrasta e apaga inteiro; texto
 * alternativo e legenda são editados pelo seletor (RichTextEditor.vue, botão "Imagem" com a
 * figura selecionada), não digitados dentro da figura. Legenda em texto simples, sem negrito:
 * o que o editor oferece é o que ele sabe devolver sem perda.
 *
 * Duas formas diferentes, de propósito:
 * - `renderHTML` é o que vai para o banco — só a forma canônica, sem largura (quem monta o
 *   `srcset` é a leitura pública, ver App\Actions\Media\ExpandContentImages);
 * - a `NodeView` é o que a pessoa VÊ no editor — a mesma imagem pela rota autenticada da API,
 *   porque `/midia/...` só existe no domínio do site.
 *
 * Quem decide se a imagem pode ir ao site é a API ao salvar (AssertContentImagesArePublishable),
 * não este nó.
 */
export const MediaFigure = Node.create({
  name: 'mediaFigure',
  group: 'block',
  atom: true,
  draggable: true,
  selectable: true,

  addAttributes() {
    return {
      uuid: { default: null },
      alt: { default: '' },
      caption: { default: '' },
    }
  },

  parseHTML() {
    return [
      {
        tag: 'figure',
        getAttrs: (element) => {
          const img = element.querySelector('img')
          const match = img?.getAttribute('src')?.match(CANONICAL_SRC)

          if (!img || !match) {
            return false
          }

          return {
            uuid: match[1],
            alt: img.getAttribute('alt') ?? '',
            caption: element.querySelector('figcaption')?.textContent?.trim() ?? '',
          }
        },
      },
    ]
  },

  renderHTML({ node }) {
    const attrs = node.attrs as MediaFigureAttrs
    const img = ['img', { src: `/midia/${attrs.uuid}`, alt: attrs.alt }] as const

    return attrs.caption ? ['figure', {}, img, ['figcaption', {}, attrs.caption]] : ['figure', {}, img]
  },

  addNodeView() {
    return ({ node }) => {
      const attrs = node.attrs as MediaFigureAttrs
      const dom = document.createElement('figure')
      dom.className = 'rich-text__figure'

      const img = document.createElement('img')
      img.src = mediaPreviewUrl(attrs.uuid)
      img.alt = attrs.alt
      dom.append(img)

      if (attrs.caption) {
        const caption = document.createElement('figcaption')
        caption.textContent = attrs.caption
        dom.append(caption)
      }

      return { dom }
    }
  },
})
