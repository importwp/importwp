import React from 'react';
import { debugError } from '../../util/debug';

export default class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false, error: null, showError: false };
    this.reset = this.reset.bind(this);
  }

  static getDerivedStateFromError(error) {
    return { hasError: true, error };
  }

  componentDidCatch(error, errorInfo) {
    debugError('React render error', error, errorInfo);
    // Always log render crashes to the console — they are otherwise silent.
    // eslint-disable-next-line no-console
    console.error('[ImportWP] Render error', error, errorInfo);
  }

  reset() {
    this.setState({ hasError: false, error: null, showError: false });
  }

  render() {
    if (this.state.hasError) {
      const { error } = this.state;
      return (
        <div
          className="iwp-error-boundary"
          style={{ background: '#FFF', padding: '10px', marginBottom: '70px' }}
        >
          <h1>Something went wrong</h1>
          <p>
            <strong>Error</strong>: {error && error.name}
            <br />
            <strong>Message</strong>: {error && error.message}
          </p>

          <p className="iwp-buttons">
            <button
              type="button"
              className="button button-primary"
              onClick={this.reset}
            >
              Try again
            </button>{' '}
            {!this.state.showError && (
              <button
                type="button"
                className="button button-secondary"
                onClick={() => {
                  this.setState({ showError: true });
                }}
              >
                Expand error info
              </button>
            )}
          </p>

          {this.state.showError && error && (
            <textarea
              style={{ width: '100%', height: '500px', color: '#666', fontSize: '10px' }}
              readOnly
              defaultValue={`Error: ${error.name}
Message: ${error.message}
Stack:
${error.stack || ''}`}
            />
          )}
        </div>
      );
    }

    return this.props.children;
  }
}
