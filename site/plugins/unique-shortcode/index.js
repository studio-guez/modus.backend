panel.plugin('modus/unique-shortcode', {
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
