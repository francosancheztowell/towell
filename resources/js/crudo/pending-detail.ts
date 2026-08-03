type HiddenElement = {
  hidden: boolean
}

export const hidePendingDetail = (element: HiddenElement): boolean => {
  if (element.hidden) {
    return false
  }

  element.hidden = true

  return true
}
