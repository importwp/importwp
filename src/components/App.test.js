import React from 'react';

import App from './App';
import { shallow } from 'enzyme';
import ArchivePage from './archive-page/ArchivePage';
import CreatePage from './create-page/CreatePage';
import SettingsPage from './settings-page/SettingsPage';
import PremiumPage from './premium-page/PremiumPage';

function createTestProps(props) {
  return {
    ...props
  };
}

// contains everything related to rendered output
describe('rendering', () => {
  let wrapper;
  const createWrapper = props => shallow(<App {...props} />);
  beforeEach(() => {
    const props = createTestProps({
      location: {
        search: {}
      }
    });
    wrapper = createWrapper(props);
  });

  it('should always render `<PageHeader/>`', () => {
    expect(wrapper.find('PageHeader')).toHaveLength(1);
  });

  it('should render `<ArchivePage/>` by default', () => {
    expect(wrapper.instance().getActiveSection()).toEqual('archive');
    expect(wrapper.instance().getPage('archive')).toEqual(<ArchivePage />);
  });

  describe('if `new` query variable set', () => {
    beforeEach(() => {
      const props = createTestProps({
        location: {
          search: {
            new: 'asd'
          }
        }
      });
      wrapper = createWrapper(props);
    });

    it('should render `<CreatePage/>`', () => {
      expect(wrapper.instance().getActiveSection()).toEqual('new');
      expect(wrapper.instance().getPage('new')).toEqual(<CreatePage />);
    });
  });

  describe('if query variable `tab` => `settings`', () => {
    beforeEach(() => {
      const props = createTestProps({
        location: {
          search: {
            tab: 'settings'
          }
        }
      });
      wrapper = createWrapper(props);
    });

    it('should render `<SettingsPage/>`', () => {
      expect(wrapper.instance().getActiveSection()).toEqual('settings');
      expect(wrapper.instance().getPage('settings')).toEqual(<SettingsPage />);
    });
  });

  describe('if query variable `tab` => `premium`', () => {
    beforeEach(() => {
      const props = createTestProps({
        location: {
          search: {
            tab: 'premium'
          }
        }
      });
      wrapper = createWrapper(props);
    });

    it('should render `<PremiumPage/>`', () => {
      expect(wrapper.instance().getActiveSection()).toEqual('premium');
      expect(wrapper.instance().getPage('premium')).toEqual(<PremiumPage />);
    });
  });
});

// contains everything related to callback functions and interactions
// describe('callbacks', () => {
//     it('should use the given value', () => {
//         expect(1).toBeTruthy();
//     });
// });

// contains tests related to react lifecycle functions
// https://medium.com/capital-one-tech/unit-testing-behavior-of-react-components-with-test-driven-development-ae15b03a3689
// describe('lifecycle', () => {
//     it('should use the given value', () => {
//         expect(1).toBeTruthy();
//     });
// });
