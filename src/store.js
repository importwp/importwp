import {configureStore} from '@reduxjs/toolkit';
import importerReducer from './features/importer/importerSlice';

export const store = configureStore({
  reducer: {
    importer: importerReducer
  },
});