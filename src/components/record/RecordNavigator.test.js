/**
 * @jest-environment jsdom
 */
import React from 'react';
import { createRoot } from 'react-dom/client';
import { act } from 'react';
import RecordNavigator from './RecordNavigator';

global.IS_REACT_ACT_ENVIRONMENT = true;

describe('RecordNavigator', () => {
  let container;
  let root;

  beforeEach(() => {
    container = document.createElement('div');
    document.body.appendChild(container);
    root = createRoot(container);
  });

  afterEach(() => {
    act(() => {
      root.unmount();
    });
    container.remove();
  });

  it('disables first/previous at the start and last/next at the end', () => {
    act(() => {
      root.render(<RecordNavigator record={0} total={3} onChange={() => {}} />);
    });

    let buttons = Array.from(container.querySelectorAll('button'));
    expect(buttons).toHaveLength(4);
    expect(buttons[0].disabled).toBe(true);
    expect(buttons[1].disabled).toBe(true);
    expect(buttons[2].disabled).toBe(false);
    expect(buttons[3].disabled).toBe(false);

    act(() => {
      root.render(<RecordNavigator record={2} total={3} onChange={() => {}} />);
    });

    buttons = Array.from(container.querySelectorAll('button'));
    expect(buttons[0].disabled).toBe(false);
    expect(buttons[1].disabled).toBe(false);
    expect(buttons[2].disabled).toBe(true);
    expect(buttons[3].disabled).toBe(true);
  });

  it('shows a 1-based record number and total', () => {
    act(() => {
      root.render(<RecordNavigator record={1} total={5} onChange={() => {}} />);
    });

    expect(container.querySelector('.iwp-preview__nav-input').value).toBe('2');
    expect(container.querySelector('.iwp-preview__nav-total').textContent).toBe(
      'of 5'
    );
  });

  it('notifies onChange when navigating', () => {
    const onChange = jest.fn();
    act(() => {
      root.render(<RecordNavigator record={1} total={4} onChange={onChange} />);
    });

    const buttons = Array.from(container.querySelectorAll('button'));
    act(() => {
      buttons[0].click();
    });
    expect(onChange).toHaveBeenCalledWith(0);

    act(() => {
      buttons[3].click();
    });
    expect(onChange).toHaveBeenCalledWith(3);

    act(() => {
      buttons[2].click();
    });
    expect(onChange).toHaveBeenCalledWith(2);
  });
});
