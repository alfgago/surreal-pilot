module.exports = {
  testEnvironment: 'node',
  roots: ['<rootDir>/vendor/pc_mcp/tests', '<rootDir>/tests/js'],
  testMatch: ['**/__tests__/**/*.js', '**/?(*.)+(spec|test).js'],
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
    '^.+\\.js$': 'babel-jest',
  },
  testTimeout: 10000,
  verbose: true,
};