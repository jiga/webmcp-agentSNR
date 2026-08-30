export default {
  extends: ['stylelint-config-standard'],
  ignoreFiles: ['dist/**', 'node_modules/**'],
  rules: {
    'selector-class-pattern': [
      '^wmcp-[a-z0-9]+(?:-[a-z0-9]+)*$',
      { message: 'Use wmcp-prefixed kebab-case class names.' },
    ],
  },
};
