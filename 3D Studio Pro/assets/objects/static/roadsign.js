// objects/static/roadsign.js — 3D модель ресурса
export function build(d = {}) {
    const g = new THREE.Group();
    const s = parseFloat(d.scale || 1.0);
    const col1 = new THREE.MeshStandardMaterial({ color: d.color || '#15803d', roughness: 0.7 });
    const col2 = new THREE.MeshStandardMaterial({ color: d.accent || '#78350f', roughness: 0.8 });
    const metalMat = new THREE.MeshStandardMaterial({ color: 0x1f2937, metalness: 0.7, roughness: 0.3 });

    const pole = new THREE.Mesh(new THREE.CylinderGeometry(0.035, 0.035, 2.4, 10), metalMat);
                    pole.position.y = 1.2;
                    const board = new THREE.Mesh(new THREE.BoxGeometry(0.75, 0.55, 0.05), new THREE.MeshStandardMaterial({ color: 0x2563eb }));
                    board.position.set(0, 2.25, 0.03);
                    const border = new THREE.Mesh(new THREE.BoxGeometry(0.68, 0.48, 0.06), new THREE.MeshBasicMaterial({ color: 0xffffff }));
                    border.position.set(0, 2.25, 0.032);
                    g.add(pole, board, border);

    g.scale.set(s, s, s);
    g.traverse(m => { if (m.isMesh) { m.castShadow = true; m.receiveShadow = true; } });
    return g;
}

export default {
    id: 'roadsign',
    category: 'static',
    staticType: 'roadsign',
    radius: 0.45,
    build
};
