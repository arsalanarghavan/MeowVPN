import js from '@eslint/js'
import globals from 'globals'
import reactHooks from 'eslint-plugin-react-hooks'
import reactRefresh from 'eslint-plugin-react-refresh'
import tseslint from 'typescript-eslint'
import { defineConfig, globalIgnores } from 'eslint/config'

const noPhysicalTextAlign = {
  'no-restricted-syntax': [
    'error',
    {
      selector: 'Literal[value=/\\btext-(left|right)\\b/]',
      message:
        'Use text-start/text-end or dash-locale helpers under dir. See .cursor/rules/dashboard-rtl.mdc',
    },
    {
      selector: 'Literal[value=/\\bflex-row-reverse\\b/]',
      message: 'Avoid flex-row-reverse for RTL-only layout; use dir and logical properties.',
    },
  ],
}

export default defineConfig([
  globalIgnores(['dist']),
  {
    files: ['**/*.{ts,tsx}'],
    extends: [
      js.configs.recommended,
      tseslint.configs.recommended,
      reactHooks.configs.flat.recommended,
      reactRefresh.configs.vite,
    ],
    languageOptions: {
      globals: globals.browser,
    },
    rules: {
      'react-refresh/only-export-components': 'off',
      'react-hooks/purity': 'off',
      'react-hooks/set-state-in-effect': 'off',
    },
  },
  {
    files: ['src/components/**/*.{ts,tsx}'],
    ignores: ['src/components/ui/**'],
    rules: noPhysicalTextAlign,
  },
  {
    files: [
      'src/lib/dash-locale.ts',
      'src/components/ui/**',
      'src/components/team-switcher.tsx',
      'src/components/dashboard-date-picker/**',
      'src/components/dashboard-datetime-picker.tsx',
    ],
    rules: {
      'no-restricted-syntax': 'off',
    },
  },
  {
    files: ['src/components/dashboard-*.tsx'],
    ignores: ['src/components/dashboard-date-picker/**'],
    rules: {
      'no-restricted-imports': [
        'error',
        {
          paths: [
            {
              name: '@/components/dashboard-date-picker/jalali-datetime-picker',
              message:
                'Use DashboardDateTimePicker from @/components/dashboard-datetime-picker',
            },
            {
              name: '@/components/dashboard-date-picker/gregorian-datetime-picker',
              message:
                'Use DashboardDateTimePicker from @/components/dashboard-datetime-picker',
            },
            {
              name: '@/components/dashboard-date-picker/dashboard-date-picker',
              message:
                'Use DashboardDatePicker from @/components/dashboard-datetime-picker',
            },
          ],
        },
      ],
    },
  },
])
