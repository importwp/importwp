import React from 'react';
import { shallow } from 'enzyme';
import ImporterListItem from './ImporterListItem';

function createTestProps(props) {
  return {
    status: {
      id: 1,
      status: 'complete',
      msg: 'Imported (98 Records, 1 Error) on 14th Sept 2019 at 12:21',
      date: '2019-07-14 12:21',
      counter: 99,
      total: 99,
      success: 98,
      error: 1,
      warning: 0
    },
    ...props
  };
}

describe('rendering', () => {
  let wrapper;
  const createWrapper = props => shallow(<ImporterListItem {...props} />);

  beforeEach(() => {
    const props = createTestProps({
      importer: {
        id: 1,
        name: 'Importer One'
      }
    });
    wrapper = createWrapper(props);
  });

  it('Should render the importer name', () => {
    expect(wrapper.find('.iwp-heading').text()).toEqual('Importer One');
  });
});
