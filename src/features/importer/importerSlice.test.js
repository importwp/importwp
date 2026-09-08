import {
  importerSlice,
  setImporter,
  setPreviewRecord,
  selectPreviewRecord,
} from './importerSlice';

describe('previewRecord session state', () => {
  const reducer = importerSlice.reducer;

  it('defaults to record 0', () => {
    const state = reducer(undefined, { type: 'unknown' });
    expect(state.previewRecord).toBe(0);
    expect(selectPreviewRecord({ importer: state })).toBe(0);
  });

  it('stores the selected preview record', () => {
    const state = reducer(undefined, setPreviewRecord(2));
    expect(state.previewRecord).toBe(2);
    expect(selectPreviewRecord({ importer: state })).toBe(2);
  });

  it('clamps invalid preview records to 0', () => {
    expect(reducer(undefined, setPreviewRecord(-3)).previewRecord).toBe(0);
    expect(reducer(undefined, setPreviewRecord('abc')).previewRecord).toBe(0);
  });

  it('resets preview record when a new importer is loaded', () => {
    let state = reducer(undefined, setPreviewRecord(4));
    state = reducer(state, setImporter({ id: 10, name: 'Next' }));
    expect(state.previewRecord).toBe(0);
    expect(state.importer).toEqual({ id: 10, name: 'Next' });
  });
});
