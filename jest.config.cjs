module.exports = {
  testEnvironment: 'jsdom',
  roots: ['<rootDir>/vendor/pc_mcp/tests', '<rootDir>/tests/js'],
  testMatch: ['**/__tests__/**/*.{js,ts,tsx}', '**/?(*.)+(spec|test).{js,ts,tsx}'],
  collectCoverageFrom: [
    'vendor/pc_mcp/src/**/*.js',
    'resources/js/**/*.js',
    '!vendor/pc_mcp/src/**/*.test.js',
    '!vendor/pc_mcp/node_modules/**',
    '!resources/js/**/*.test.js',
  ],
  coverageDirectory: 'coverage',
  coverageReporters: ['text', 'lcov', 'html'],
  setupFilesAfterEnv: ['<rootDir>/vendor/pc_mcp/tests/setup.js'],
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/vendor/pc_mcp/src/$1',
  },
  transform: {
    '^.+\\.(js|ts|tsx)$': 'babel-jest',
  },
  testTimeout: 10000,
  verbose: true,
};