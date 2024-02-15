import './InputButton.scss';

export default function InputButton({
    type = 'button',
    theme = 'primary',
    onClick = () => { },
    children
}) {
    return (
        <button
            className={`iwp-input-button iwp-input-button--${theme}`}
            type={type}
            onClick={onClick}>
            {children}
        </button>
    );
}