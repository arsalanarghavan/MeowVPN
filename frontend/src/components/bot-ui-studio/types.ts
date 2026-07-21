export type UiButtonStyle = "" | "primary" | "success" | "danger"

export type UiStudioCell = {
  id: string
  enabled?: boolean
  glass?: boolean
  style?: UiButtonStyle
  iconCustomEmojiId?: string
}

export type UiSurfaceAction = {
  id: string
  textKey?: string
  glassDefault?: boolean
  labelFa?: string
  labelEn?: string
}
