import React from 'react';
export default class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false, error: '', showError: false };
  }

  static getDerivedStateFromError(error) {
    // Update state so the next render will show the fallback UI.
    return { hasError: true, error };
  }

  componentDidCatch(error, errorInfo) {
    // You can also log the error to an error reporting service
    // logErrorToMyService(error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      // You can render any custom fallback UI
      return <div style={{ background: '#FFF', padding: '10px', marginBottom: '70px' }}>
        <h1>Something went wrong</h1>
        <p><strong>Error</strong>: {this.state.error.name}<br /><strong>Message</strong>: {this.state.error.message}</p>

        {!this.state.showError && (
          <button className='button-secondary' onClick={() => {
            this.setState({ showError: true });
          }}>Expand error info</button>
        )}

        {this.state.showError && (
          <textarea style={{ width: '100%', height: '500px', color: '#666', fontSize: '10px' }} defaultValue={
            `Error: ${this.state.error.name}
Message: ${this.state.error.message}
Stack:
${this.state.error.stack}`
          } />
        )}
      </div>
    }

    return this.props.children;
  }
}
