import Enzyme from 'enzyme';
import Adapter from 'enzyme-adapter-react-16';

Enzyme.configure({ adapter: new Adapter() });

global.iwp = {
  root: '',
  nonce: '',
  ajax_base: '',
  admin_base: '',
};
