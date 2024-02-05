import {
  createAsyncThunk,
  createSelector,
  createSlice,
} from '@reduxjs/toolkit';
import { shallowEqual } from 'react-redux';
import { importer } from '../../services/importer.service';

const initialState = {
  importer: null,
  previews: {},
  template: {},
  enabled: {},
  repeater: {},
};

// https://redux-toolkit.js.org/usage/usage-guide#async-requests-with-createasyncthunk
export const fetchFieldPreview = createAsyncThunk(
  'importer/fetchFieldPreview',
  async (data) => {
    const { id, fields } = data;

    // transform keys to preview_ids
    let input = {};
    Object.keys(fields).forEach((key) => {
      input = {
        ...input,
        [key.replace(/\./g, '_')]: fields[key],
      };
    });

    try {
      const response = await importer.recordPreview(id, { ...input });

      // transform preview_ids to keys
      let output = {};
      Object.keys(fields).forEach((key) => {
        const key_id = key.replace(/\./g, '_');
        output = {
          ...output,
          [key]: response[key_id],
        };
      });

      return output;
    } catch (e) {
      return {
        [field]: 'Unable to load preview',
      };
    }
  }
);

export const importerSlice = createSlice({
  name: 'importer',
  initialState,
  reducers: {
    setImporter: (state, action) => {
      state.importer = action.payload;
    },
    setPreview: (state, action) => {
      state.previews = {
        ...state.previews,
        ...action.payload,
      };
    },
    resetTemplate: (state, action) => {
      state.template = {
        ...action.payload,
      };
    },
    setTemplate: (state, action) => {
      state.template = {
        ...state.template,
        ...action.payload,
      };
    },
    resetEnabled: (state, action) => {
      state.enabled = {
        ...action.payload,
      };
    },
    setEnabled: (state, action) => {
      state.enabled = {
        ...state.enabled,
        ...action.payload,
      };
    },
    resetRepeater: (state, action) => {
      state.repeater = {
        ...action.payload,
      };
    },
    addMapFieldRow(state, action) {
      const key = action.payload;
      const prefix = key;
      const lastIndex = state.template.hasOwnProperty(`${prefix}._index`)
        ? +state.template[`${prefix}._index`]
        : 0;

      let tmp = {};
      Object.keys(state.repeater[key]).forEach((element) => {
        tmp = {
          ...tmp,
          [prefix + '.' + lastIndex + '.' + element]:
            state.repeater[key][element],
        };
      });

      tmp = {
        ...tmp,
        [prefix + '._index']: lastIndex + 1,
      };

      state.template = {
        ...state.template,
        ...tmp,
      };
    },

    removeMapFieldRow(state, action) {
      const { id: key, index } = action.payload;

      const prefix = key;
      const rowCount = state.template[prefix + '._index'];
      const newRowCount = rowCount - 1;

      let tmp = {};
      for (let i = index; i < newRowCount; i++) {
        Object.keys(state.repeater[key]).forEach((element) => {
          tmp = {
            ...tmp,
            [prefix + '.' + i + '.' + element]:
              state.template[prefix + '.' + (i + 1) + '.' + element],
          };
        });
      }

      // null the last row, since rows have been shifted
      Object.keys(state.repeater[key]).forEach((element) => {
        tmp = {
          ...tmp,
          [prefix + '.' + newRowCount + '.' + element]: null,
        };
      });

      tmp = {
        ...tmp,
        [prefix + '._index']: newRowCount,
      };

      state.template = {
        ...state.template,
        ...tmp,
      };
    },
    clearPreview(state) {
      state.previews = {};
    },
  },
  extraReducers: (builder) => {
    builder.addCase(fetchFieldPreview.fulfilled, (state, action) => {
      state.previews = {
        ...state.previews,
        ...action.payload,
      };
    });
  },
});

export const {
  test,
  setImporter,
  setPreview,
  setTemplate,
  setEnabled,
  resetRepeater,
  addMapFieldRow,
  removeMapFieldRow,
  resetTemplate,
  resetEnabled,
  clearPreview,
} = importerSlice.actions;

export const selectMap = (state) => state.importer.importer.map;
export const selectPreviews = (state) => state.importer.previews;
export const seletValues = (state) => state.importer.template;
export const seletEnabled = (state) => state.importer.enabled;
export const seletRepeaterTemplates = (state) => state.importer.repeater;
export const selectId = (state, id) => id;

const selectorSettings = {
  memoizeOptions: {
    equalityCheck: (a, b) => a === b,
    maxSize: 100,
    resultEqualityCheck: shallowEqual,
  },
};

export const getPreview = createSelector(
  [selectPreviews, selectId],
  (items, itemId) => (items.hasOwnProperty(itemId) ? items[itemId] : '')
);

export const getValue = createSelector(
  [seletValues, selectId],
  (items, itemId) => (items.hasOwnProperty(itemId) ? items[itemId] : '')
);

export const getFieldMap = createSelector(
  [seletValues, selectId],
  (items, itemId) => {
    const result = Object.keys(items)
      .filter((fieldKey) => fieldKey.startsWith(itemId))
      .reduce((obj, key) => {
        // obj[key.substring(itemId.length + 1)] = items[key];
        obj[key] = items[key];
        return obj;
      }, {});

    return result;
  },
  selectorSettings
);

export const getEnabledMap = createSelector(
  [seletEnabled, selectId],
  (items, itemId) => {
    return Object.keys(items)
      .filter((fieldKey) => fieldKey.startsWith(itemId))
      .reduce((obj, key) => {
        obj[key] = items[key];
        return obj;
      }, {});
  },
  selectorSettings
);

export const getRepeaterFields = createSelector(
  [seletValues, selectId],
  (items, itemId) => {
    const result = Object.keys(items)
      .filter((fieldKey) => fieldKey.startsWith(itemId))
      .reduce((obj, key) => {
        // obj[key.substring(itemId.length + 1)] = items[key];
        obj[key] = items[key];
        return obj;
      }, {});

    let output = [];

    for (let i = 0; i < result[itemId + '._index']; i++) {
      output = [
        ...output,
        Object.keys(result)
          .filter((value) => {
            // we need the final . otherwise 1 matches 10,11
            return value.startsWith(itemId + '.' + i + '.');
          })
          .reduce((obj, key) => {
            obj[key] = result[key];
            return obj;
          }, {}),
      ];
    }

    return output;
  },
  selectorSettings
);

export default importerSlice.reducer;
