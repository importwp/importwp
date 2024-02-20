import './InputButton.scss';

export default function InputButton({
    type = 'button',
    theme = 'primary',
    onClick = () => { },
    disabled = false,
    children
}) {
    return (
        <button
            disabled={disabled}
            className={`iwp-input-button iwp-input-button--${theme}`}
            type={type}
            onClick={onClick}>
            {children}
        </button>
    );
}