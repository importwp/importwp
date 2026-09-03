import React from 'react';
import { createRoot } from '@wordpress/element';
import { BrowserRouter as Router, Route } from 'react-router-dom';
import App from './components/App';
import { createHooks } from '@wordpress/hooks';
// import domReady from '@wordpress/dom-ready';
import { Provider } from 'react-redux';
import { store } from './store';
import ErrorBoundary from './components/error-boundary/ErrorBoundary';

window.iwp = window.iwp || {};
window.iwp.hooks = createHooks();

// Allow wordpress to enter a global notice
window.iwp.hooks.addFilter('iwp_global_notices', 'importwp', () => window.iwp.global_notices);

const routes = (
  <Provider store={store}>
    <ErrorBoundary>
      <div className="wrap">
        <Router>
          <Route path="/" component={App} />
        </Router>
      </div>
    </ErrorBoundary>
  </Provider>
);

// domReady(() => {
const root = createRoot(document.getElementById('importwp-root'));
root.render(routes);
// });
