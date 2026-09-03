jest.mock('../../services/importer.service', () => ({
  importer: {
    remove: jest.fn(() => Promise.resolve()),
  },
}));

import React from 'react';
import ImporterListItem from './ImporterListItem';
import {
  getSectionProgress,
  getSectionProgressBarWidth,
} from './progress';

const runningStatusWithEmptySection = {
  id: 17284,
  status: 'running',
  section: '',
  message: null,
  progress: {
    simple: { current_row: 0, start: 0, end: 100 },
    delete: { current_row: 0, start: 0, end: 0 },
    import: { current_row: 50, start: 0, end: 100 },
  },
};

const runningStatusWithValidSection = {
  id: 1,
  status: 'running',
  section: 'import',
  message: null,
  progress: {
    import: { current_row: 25, start: 0, end: 100 },
  },
};

beforeAll(() => {
  window.iwp = {
    admin_base: '/wp-admin/admin.php?page=importwp',
    ajax_base: '/wp-admin/admin-ajax.php',
  };
});

describe('progress helpers', () => {
  it('returns null when section is empty', () => {
    expect(getSectionProgress(runningStatusWithEmptySection)).toBeNull();
    expect(getSectionProgressBarWidth(runningStatusWithEmptySection)).toBeNull();
  });

  it('returns a progress bar width when section progress exists', () => {
    expect(getSectionProgress(runningStatusWithValidSection)).toEqual({
      current_row: 25,
      start: 0,
      end: 100,
    });
    expect(getSectionProgressBarWidth(runningStatusWithValidSection)).toBe(75);
  });
});

function hasProgressBar(element) {
  if (!element || typeof element !== 'object') {
    return false;
  }

  if (element.props?.className === 'iwp-item__progress-bar') {
    return true;
  }

  return React.Children.toArray(element.props?.children).some(hasProgressBar);
}

describe('ImporterListItem rendering', () => {
  it('does not crash when running with an empty section', () => {
    const props = {
      importer: {
        id: 17284,
        name: 'WooCommerce Importer',
        parser: 'csv',
        template: 'post',
      },
      status: runningStatusWithEmptySection,
      onDelete: () => {},
    };

    expect(() => ImporterListItem(props)).not.toThrow();
  });

  it('renders a progress bar when section progress is available', () => {
    const element = ImporterListItem({
      importer: {
        id: 1,
        name: 'Importer One',
        parser: 'csv',
        template: 'post',
      },
      status: runningStatusWithValidSection,
      onDelete: () => {},
    });

    expect(hasProgressBar(element)).toBe(true);
  });

  it('does not render a progress bar when section progress is missing', () => {
    const element = ImporterListItem({
      importer: {
        id: 17284,
        name: 'WooCommerce Importer',
        parser: 'csv',
        template: 'post',
      },
      status: runningStatusWithEmptySection,
      onDelete: () => {},
    });

    expect(hasProgressBar(element)).toBe(false);
  });
});
