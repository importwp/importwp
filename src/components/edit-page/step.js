import qs from 'qs';

export function computeMaxStep(importerData = {}) {
  let max = 0;

  if (importerData.file && importerData.file.id > 0) {
    max = 1;
  }

  if (
    importerData.file &&
    importerData.file.settings &&
    importerData.file.settings.setup === true
  ) {
    max = 2;
  }

  if (importerData.map && Object.keys(importerData.map).length > 0) {
    max = 3;
  }

  const hasNewUniqueIdentifierUI =
    +importerData?.version >= 2 || importerData.settings?.unique_identifier_type;

  if (importerData.permissions && (
    !hasNewUniqueIdentifierUI ||
    (importerData.settings?.unique_identifier_type === 'field' && importerData.settings?.unique_identifier?.length > 0) ||
    (importerData.settings?.unique_identifier_type === 'custom' && importerData.settings?.unique_identifier_ref?.length > 0)
  )) {
    if (
      (importerData.permissions.create &&
        importerData.permissions.create.enabled === true) ||
      (importerData.permissions.update &&
        importerData.permissions.update.enabled === true) ||
      (importerData.permissions.remove &&
        importerData.permissions.remove.enabled === true)
    ) {
      max = 5;
    }
  }

  return max;
}

export function getStepFromSearch(search, maxStep) {
  const values = qs.parse(search, { ignoreQueryPrefix: true });
  if (values.step) {
    const requestedStep = parseInt(values.step, 10);
    if (Number.isNaN(requestedStep)) {
      return maxStep > 4 ? 4 : maxStep;
    }
    return Math.min(maxStep, requestedStep);
  }
  return maxStep > 4 ? 4 : maxStep;
}

export function getStepFromImporterAndSearch(importerData, search) {
  return getStepFromSearch(search, computeMaxStep(importerData));
}

export function shouldPushStepUrl({ init, id, currentUrlStep, step }) {
  if (!init || id === null) {
    return false;
  }

  return currentUrlStep !== step;
}
