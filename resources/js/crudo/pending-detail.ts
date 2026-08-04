type HiddenElement = {
  hidden: boolean | string
}

export const hidePendingDetail = (element: HiddenElement): boolean => {
  if (element.hidden) {
    return false
  }

  element.hidden = true

  return true
}

export const isIntentionalMachineActivation = (
  pointerTelar: string | null,
  clickedTelar: string,
  clickDetail: number,
): boolean => clickDetail === 0 || pointerTelar === clickedTelar
