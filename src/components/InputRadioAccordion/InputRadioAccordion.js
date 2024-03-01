import { useState, Children, cloneElement } from 'react'
import './InputRadioAccordion.scss';

export default function InputRadioAccordion({
    name,
    children,
    defaultActive = '',
    onChange = () => { }
}) {

    const [activePanel, setActivePanel] = useState(defaultActive);

    return (
        <div className="iwp-accordion">
            {Children.map(children, (child, i) => {
                const isActive = child.props.value === activePanel;
                return cloneElement(child, {
                    name,
                    isActive,
                    setActive: (value) => {
                        setActivePanel(value);
                        onChange(value);
                    }
                });
            })}
        </div>
    );
}