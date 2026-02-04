panel.plugin('oplus/custom-marks', {
  writerMarks: {
    highlight: {
      get button() {
        return {
          icon: 'badge',
          label: 'Surligner'
        };
      },
      commands() {
        return () => this.toggle();
      },
      get name() {
        return 'highlight';
      },
      get schema() {
        return {
          parseDOM: [{ tag: 'mark' }],
          toDOM: () => ['mark', 0]
        };
      }
    }
  }
});
