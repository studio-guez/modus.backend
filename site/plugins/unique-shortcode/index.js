panel.plugin('oplus/unique-shortcode', {
  fields: {
    'unique-shortcode': {
      props: {
        prefix: {
          type: String,
          default: 'ref'
        },
        value: {
          type: String,
          default: ''
        },
        label: String,
        disabled: Boolean,
        required: Boolean,
        help: String
      },
      data() {
        return {
          localValue: this.value
        }
      },
      watch: {
        value(newVal) {
          this.localValue = newVal;
        }
      },
      created() {
        if (!this.value) {
          const id = Math.random().toString(36).substring(2, 10);
          const shortcode = '[' + this.prefix + ':' + id + ']';
          this.localValue = shortcode;
          this.$emit('input', shortcode);
        }
      },
      template: `
        <k-field
          :label="label"
          :disabled="disabled"
          :required="required"
          :help="help"
          class="k-autoid-field"
        >
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <k-text-input
              :value="localValue"
              :disabled="true"
              style="flex: 1;"
            />
            <k-button
              icon="copy"
              size="sm"
              :title="'Copier: ' + localValue"
              @click="copyShortcode"
            />
          </div>
        </k-field>
      `,
      methods: {
        copyShortcode() {
          navigator.clipboard.writeText(this.localValue).then(() => {
            this.$panel.notification.success('Copié: ' + this.localValue);
          }).catch(() => {
            const el = document.createElement('textarea');
            el.value = this.localValue;
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
            this.$panel.notification.success('Copié: ' + this.localValue);
          });
        }
      }
    }
  }
});

// Highlight shortcodes in writer fields using CSS Custom Highlight API
// This is purely visual and does not modify the DOM
(function () {
  if (typeof CSS === 'undefined' || !CSS.highlights) return;

  const SHORTCODE_RE = /\[(ref|figure):[a-zA-Z0-9]+\]/g;
  let rafId = null;

  function updateHighlights() {
    const ranges = [];
    document.querySelectorAll('.k-writer').forEach(function (writer) {
      const walker = document.createTreeWalker(writer, NodeFilter.SHOW_TEXT);
      while (walker.nextNode()) {
        const node = walker.currentNode;
        const text = node.textContent || '';
        SHORTCODE_RE.lastIndex = 0;
        let match;
        while ((match = SHORTCODE_RE.exec(text)) !== null) {
          const range = new Range();
          range.setStart(node, match.index);
          range.setEnd(node, match.index + match[0].length);
          ranges.push(range);
        }
      }
    });
    if (ranges.length > 0) {
      CSS.highlights.set('shortcode-hl', new Highlight(...ranges));
    } else {
      CSS.highlights.delete('shortcode-hl');
    }
  }

  function scheduleUpdate() {
    if (rafId) return;
    rafId = requestAnimationFrame(function () {
      rafId = null;
      updateHighlights();
    });
  }

  var observer = new MutationObserver(scheduleUpdate);

  function start() {
    observer.observe(document.body, {
      childList: true,
      subtree: true,
      characterData: true
    });
    updateHighlights();
  }

  if (document.readyState === 'complete') {
    start();
  } else {
    window.addEventListener('load', start);
  }
})();
