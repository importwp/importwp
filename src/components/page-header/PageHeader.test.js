import React from 'react';

import { shallow } from 'enzyme';
import PageHeader from './PageHeader';

function createTestProps(props) {
  return {
    ...props
  };
}

// contains everything related to rendered output
describe('rendering', () => {
  let wrapper;
  const createWrapper = props => shallow(<PageHeader {...props} />);
  beforeEach(() => {
    const props = createTestProps({});
    wrapper = createWrapper(props);
  });

  it('displays archive tab by default', () => {
    expect(
      wrapper
        .find('Link.iwp-header__tab--active')
        .children()
        .text()
    ).toEqual('Importers');
  });

  describe('if active prop set to `new`', () => {
    beforeEach(() => {
      const props = createTestProps({
        active: 'new'
      });
      wrapper = createWrapper(props);
    });
    it('highlights `Add new` tab', () => {
      expect(
        wrapper
          .find('Link.iwp-header__tab--active')
          .children()
          .text()
      ).toEqual('Add New');
    });
  });

  describe('if active prop set to `premium`', () => {
    beforeEach(() => {
      const props = createTestProps({
        active: 'premium'
      });
      wrapper = createWrapper(props);
    });
    it('highlights `Add premium` tab', () => {
      expect(
        wrapper
          .find('Link.iwp-header__tab--active')
          .children()
          .text()
      ).toEqual('Premium');
    });
  });

  describe('if active prop set to `settings`', () => {
    beforeEach(() => {
      const props = createTestProps({
        active: 'settings'
      });
      wrapper = createWrapper(props);
    });
    it('highlights `Add settings` tab', () => {
      expect(
        wrapper
          .find('Link.iwp-header__tab--active')
          .children()
          .text()
      ).toEqual('Settings');
    });
  });
});
