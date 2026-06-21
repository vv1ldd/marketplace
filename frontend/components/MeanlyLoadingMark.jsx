import { VaultShieldIcon } from './IdentityVaultIcons';

export function MeanlyLoadingMark({
  size = 'md',
  label = '',
  className = '',
  iconOnly = false,
}) {
  const icon = (
    <span className={`meanly-loading-mark__icon meanly-loading-mark__icon--${size}`} aria-hidden="true">
      <VaultShieldIcon />
    </span>
  );

  if (iconOnly) {
    return icon;
  }

  return (
    <div
      className={`meanly-loading-mark meanly-loading-mark--${size}${className ? ` ${className}` : ''}`.trim()}
      role="status"
      aria-live="polite"
    >
      {icon}
      {label ? <span className="meanly-loading-mark__label">{label}</span> : null}
    </div>
  );
}
