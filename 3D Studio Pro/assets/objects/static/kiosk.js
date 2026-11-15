// objects/static/kiosk.js — 3D модель ресурса
export function build(d = {}) {
    const g = new THREE.Group();
    const s = parseFloat(d.scale || 1.0);
    const col1 = new THREE.MeshStandardMaterial({ color: d.color || '#15803d', roughness: 0.7 });
    const col2 = new THREE.MeshStandardMaterial({ color: d.accent || '#78350f', roughness: 0.8 });
    const metalMat = new THREE.MeshStandardMaterial({ color: 0x1f2937, metalness: 0.7, roughness: 0.3 });

    const body = new THREE.Mesh(new THREE.BoxGeometry(2.2, 2.3, 1.8), col1);
                    body.position.y = 1.15;
                    const roof = new THREE.Mesh(new THREE.BoxGeometry(2.5, 0.25, 2.1), col2);
                    roof.position.y = 2.42;
                    const win = new THREE.Mesh(new THREE.PlaneGeometry(1.4, 0.9), new THREE.MeshBasicMaterial({ color: 0xbae6fd }));
                    win.position.set(0, 1.35, 0.91);
                    const counter = new THREE.Mesh(new THREE.BoxGeometry(1.6, 0.08, 0.35), col2);
                    counter.position.set(0, 0.88, 1.05);
                    g.add(body, roof, win, counter);

    g.scale.set(s, s, s);
    g.traverse(m => { if (m.isMesh) { m.castShadow = true; m.receiveShadow = true; } });
    return g;
}

export default {
    id: 'kiosk',
    category: 'static',
    staticType: 'kiosk',
    radius: 0.85,
    build
};
