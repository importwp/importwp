export function getSectionProgress(status) {
  if (!status?.progress || !status?.section) {
    return null;
  }

  const progress = status.progress[status.section];

  if (
    !progress ||
    typeof progress.current_row !== 'number' ||
    typeof progress.start !== 'number' ||
    typeof progress.end !== 'number'
  ) {
    return null;
  }

  const range = progress.end - progress.start;

  if (range <= 0) {
    return null;
  }

  return progress;
}

export function getSectionProgressBarWidth(status) {
  const progress = getSectionProgress(status);

  if (!progress) {
    return null;
  }

  return 100 - (progress.current_row / (progress.end - progress.start)) * 100;
}
