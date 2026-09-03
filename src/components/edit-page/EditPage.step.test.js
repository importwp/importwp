import {
  computeMaxStep,
  getStepFromImporterAndSearch,
  getStepFromSearch,
  shouldPushStepUrl,
} from './step';

const configuredImporter = {
  version: 2,
  file: {
    id: 10,
    settings: {
      setup: true,
    },
  },
  map: {
    'post.title': '{/name}',
  },
  permissions: {
    create: { enabled: true },
    update: { enabled: true },
    remove: { enabled: false },
  },
  settings: {
    unique_identifier_type: 'field',
    unique_identifier: 'post.title',
  },
};

describe('computeMaxStep', () => {
  it('returns 0 when the importer has no file', () => {
    expect(computeMaxStep({})).toBe(0);
  });

  it('returns 1 when a file has been selected', () => {
    expect(
      computeMaxStep({
        file: { id: 10, settings: {} },
      })
    ).toBe(1);
  });

  it('returns 2 when file settings are complete', () => {
    expect(
      computeMaxStep({
        file: { id: 10, settings: { setup: true } },
      })
    ).toBe(2);
  });

  it('returns 3 when template fields have been mapped', () => {
    expect(
      computeMaxStep({
        file: { id: 10, settings: { setup: true } },
        map: { 'post.title': '{/name}' },
      })
    ).toBe(3);
  });

  it('returns 5 when permissions are configured', () => {
    expect(computeMaxStep(configuredImporter)).toBe(5);
  });
});

describe('getStepFromSearch', () => {
  it('restores the requested step from a refresh URL', () => {
    expect(
      getStepFromSearch(
        '?page=importwp&edit=336&step=2',
        5
      )
    ).toBe(2);
  });

  it('restores the requested step without a leading question mark', () => {
    expect(getStepFromSearch('page=importwp&edit=336&step=2', 5)).toBe(2);
  });

  it('clamps the requested step to the importer max step', () => {
    expect(getStepFromSearch('?page=importwp&edit=336&step=5', 2)).toBe(2);
  });

  it('defaults to the max step when the URL has no step', () => {
    expect(getStepFromSearch('?page=importwp&edit=336', 3)).toBe(3);
  });

  it('defaults to step 4 when max step is history', () => {
    expect(getStepFromSearch('?page=importwp&edit=336', 5)).toBe(4);
  });
});

describe('getStepFromImporterAndSearch', () => {
  it('keeps template fields selected after a refresh', () => {
    expect(
      getStepFromImporterAndSearch(
        configuredImporter,
        '?page=importwp&edit=336&step=2'
      )
    ).toBe(2);
  });

  it('does not fall back to step 0 when max step is available', () => {
    expect(
      getStepFromImporterAndSearch(
        configuredImporter,
        '?page=importwp&edit=336&step=2'
      )
    ).not.toBe(0);
  });

  it('does not restore a later step than the importer has completed', () => {
    expect(
      getStepFromImporterAndSearch(
        {
          file: { id: 10, settings: {} },
        },
        '?page=importwp&edit=336&step=2'
      )
    ).toBe(1);
  });
});

describe('shouldPushStepUrl', () => {
  it('does not rewrite the URL before the importer has loaded', () => {
    expect(
      shouldPushStepUrl({
        init: false,
        id: 336,
        currentUrlStep: 2,
        step: 0,
      })
    ).toBe(false);
  });

  it('does not rewrite the URL when the current step already matches', () => {
    expect(
      shouldPushStepUrl({
        init: true,
        id: 336,
        currentUrlStep: 2,
        step: 2,
      })
    ).toBe(false);
  });

  it('pushes the URL after the user changes step', () => {
    expect(
      shouldPushStepUrl({
        init: true,
        id: 336,
        currentUrlStep: 2,
        step: 3,
      })
    ).toBe(true);
  });
});
