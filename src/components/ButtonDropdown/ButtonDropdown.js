import React, { useRef, useEffect, useState } from 'react';
import './ButtonDropdown.scss';

export default function ButtonDropdown({ items }) {

    const wrapper = useRef(null);
    const [showDropdown, setShowDropdown] = useState(false);

    useEffect(() => {
        document.addEventListener('mousedown', handleClickOutside);
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        }
    }, [])

    function handleClickOutside(event) {
        if (wrapper.current && !wrapper.current.contains(event.target)) {
            setShowDropdown(false);
        }
    }

    function close() {
        setShowDropdown(false);
    }

    return (
        <div className="iwp-dropdown" ref={wrapper}>
            <button
                type="button"
                className="button button-secondary"
                onClick={() => {
                    setShowDropdown(!showDropdown);
                }}
            >
                Enable Fields
            </button>
            {showDropdown && (<div className='iwp-dropdown__popover'>
                <div className='iwp-dropdown__scroll'>
                    {items(close)}
                </div>
            </div>)}
        </div>
    )
};